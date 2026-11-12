<x-layouts.app title="Drive Explorer" :active-menu="'drive'">

    {{-- ===== Page Header ===== --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 sm:gap-4 mb-4 sm:mb-6 min-w-0 max-w-full w-full">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h2 class="text-xl font-bold text-slate-800">Drive Explorer</h2>
                @if (isset($folderStatus) && !empty($isLeafFolder))
                @php
                    $statusNorm = strtolower(trim($folderStatus ?? ''));
                @endphp
                <div class="relative inline-block text-left" id="status-dropdown-wrapper">
                    <button type="button"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold border transition-all focus:outline-none shadow-2xs cursor-pointer"
                            style="background-color: {{ $statusNorm === 'complete' ? '#F0FDF4' : ($statusNorm === 'pending' ? '#FFFBEB' : '#FEF2F2') }}; color: {{ $statusNorm === 'complete' ? '#15803D' : ($statusNorm === 'pending' ? '#B45309' : '#B91C1C') }}; border-color: {{ $statusNorm === 'complete' ? '#86EFAC' : ($statusNorm === 'pending' ? '#FDE68A' : '#FECACA') }};"
                            id="btn-status-dropdown"
                            onclick="toggleStatusDropdown()"
                            title="Klik untuk memilih/mengubah status ceklist">
                        <span class="inline-block w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $statusNorm === 'complete' ? '#22C55E' : ($statusNorm === 'pending' ? '#F59E0B' : '#EF4444') }};"></span>
                        <span>
                            @if ($statusNorm === 'complete')
                                Sudah Ceklist (Complete)
                            @elseif ($statusNorm === 'pending')
                                Pending Finalisasi
                            @else
                                Belum Upload
                            @endif
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5 opacity-60">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <div id="status-dropdown-menu" class="hidden absolute left-0 mt-1.5 w-64 rounded-2xl bg-white border border-slate-100 shadow-xl p-1.5 z-50 origin-top-left focus:outline-none">
                        <div class="px-2.5 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pilih Status Ceklist</div>

                        {{-- Option: Complete --}}
                        <form action="{{ route('drive.folder.update-status') }}" method="POST" class="block">
                            @csrf
                            <input type="hidden" name="folder_id" value="{{ $folderId }}">
                            <input type="hidden" name="status" value="complete">
                            <button type="submit" class="w-full text-left px-3 py-2.5 rounded-xl hover:bg-emerald-50 transition-colors flex items-center gap-2.5 group {{ $statusNorm === 'complete' ? 'bg-emerald-50/80' : '' }}">
                                <span class="inline-block w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: #22C55E !important;"></span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-1">
                                        <p class="text-xs font-bold text-emerald-700">Sudah Ceklist (Complete)</p>
                                        @if ($statusNorm === 'complete')
                                            <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </div>
                                    <p class="text-[10px] text-slate-400">Validasi folder & centang di Sheet</p>
                                </div>
                            </button>
                        </form>

                        {{-- Option: Pending Finalisasi --}}
                        <form action="{{ route('drive.folder.update-status') }}" method="POST" class="block mt-0.5">
                            @csrf
                            <input type="hidden" name="folder_id" value="{{ $folderId }}">
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="w-full text-left px-3 py-2.5 rounded-xl hover:bg-amber-50 transition-colors flex items-center gap-2.5 group {{ $statusNorm === 'pending' ? 'bg-amber-50/80' : '' }}">
                                <span class="inline-block w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: #F59E0B !important;"></span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-1">
                                        <p class="text-xs font-bold text-amber-700">Pending Finalisasi</p>
                                        @if ($statusNorm === 'pending')
                                            <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </div>
                                    <p class="text-[10px] text-slate-400">Status draft sebelum final</p>
                                </div>
                            </button>
                        </form>

                        {{-- Option: Belum Upload --}}
                        <form action="{{ route('drive.folder.update-status') }}" method="POST" class="block mt-0.5">
                            @csrf
                            <input type="hidden" name="folder_id" value="{{ $folderId }}">
                            <input type="hidden" name="status" value="incomplete">
                            <button type="submit" class="w-full text-left px-3 py-2.5 rounded-xl hover:bg-red-50 transition-colors flex items-center gap-2.5 group {{ $statusNorm === 'incomplete' ? 'bg-red-50/80' : '' }}">
                                <span class="inline-block w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: #EF4444 !important;"></span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-1">
                                        <p class="text-xs font-bold text-red-700">Belum Upload</p>
                                        @if ($statusNorm === 'incomplete')
                                            <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </div>
                                    <p class="text-[10px] text-slate-400">Tandai folder belum lengkap</p>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
                @endif
            </div>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">Jelajahi, upload, dan kelola dokumen Google Drive</p>
        </div>

        @if ($folderId && !$error)
        <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0 max-w-full min-w-0 w-full sm:w-auto shrink-0" style="scrollbar-width:none; -ms-overflow-style:none;">
            <x-button id="btn-upload-trigger"
                      variant="primary"
                      class="text-xs sm:text-sm py-2 px-3 sm:py-2.5 sm:px-4 shrink-0 whitespace-nowrap"
                      icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />'
                      onclick="openModal('modal-upload')">
                Upload File
            </x-button>

            <x-button id="btn-create-folder-trigger"
                      variant="secondary"
                      class="text-xs sm:text-sm py-2 px-3 sm:py-2.5 sm:px-4 shrink-0 whitespace-nowrap"
                      icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />'
                      onclick="openModal('modal-create-folder')">
                Buat Folder
            </x-button>

            <x-button id="btn-auto-generate-trigger"
                      variant="secondary"
                      class="bg-white text-slate-700 border-slate-200 hover:bg-slate-50 font-medium shadow-2xs text-xs sm:text-sm py-2 px-3 sm:py-2.5 sm:px-4 shrink-0 whitespace-nowrap"
                      icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />'
                      onclick="handleAutoGenerateBtnClick(event)">
                Auto Generate Folder
            </x-button>
        </div>
        @endif
    </div>

    {{-- ===== Skeleton Loading — hanya tampil jika request > 300ms ===== --}}
    @if ($folderId && !$error)
    <div data-skeleton class="is-hidden w-full" id="sk-drive">
        {{-- Breadcrumb Skeleton --}}
        <div class="mb-5 bg-white rounded-2xl border border-slate-100 shadow-sm px-4 py-3.5 w-full">
            <div class="flex items-center gap-2">
                <div class="skeleton-text w-16"></div>
                <div class="skeleton-circle w-3 h-3"></div>
                <div class="skeleton-text w-20"></div>
                <div class="skeleton-circle w-3 h-3"></div>
                <div class="skeleton-text w-24"></div>
            </div>
        </div>
        {{-- Table Skeleton --}}
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden w-full">
            <div class="px-5 py-3 border-b border-slate-50 flex items-center justify-between">
                <div class="skeleton-text w-28"></div>
                <div class="flex gap-2">
                    <div class="skeleton w-20 h-8 rounded-lg"></div>
                    <div class="skeleton w-24 h-8 rounded-lg"></div>
                </div>
            </div>
            {{-- Header row --}}
            <div class="flex items-center gap-3 px-5 py-2.5 border-b border-slate-50">
                <div class="skeleton-circle w-5 h-5"></div>
                <div class="skeleton-text flex-1"></div>
                <div class="skeleton-text w-16"></div>
                <div class="skeleton-text w-20"></div>
                <div class="skeleton-text w-16"></div>
            </div>
            @for ($i = 0; $i < 5; $i++)
            <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-50 last:border-0">
                <div class="skeleton-circle w-8 h-8 flex-shrink-0"></div>
                <div class="skeleton-row flex-1"></div>
                <div class="skeleton-text w-16 flex-shrink-0"></div>
                <div class="skeleton-text w-20 flex-shrink-0"></div>
                <div class="flex gap-1.5 flex-shrink-0">
                    <div class="skeleton w-7 h-7 rounded-lg"></div>
                    <div class="skeleton w-7 h-7 rounded-lg"></div>
                </div>
            </div>
            @endfor
        </div>
    </div>
    @endif

    {{-- ===== Real Content ===== --}}
    <div data-content class="sk-content w-full">

    {{-- ===== Error State ===== --}}
    {{-- ===== Breadcrumb ===== --}}
    @if (!empty($breadcrumb))
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-5 w-full">
        <x-breadcrumb :items="$breadcrumb" />
    </div>
    @endif

    @if ($error)
        <div class="mb-5 w-full"><x-alert type="error" :message="$error" /></div>
    @endif

    {{-- ===== Not Configured ===== --}}
    @if (!$folderId && !$error)
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-12 text-center w-full">
            <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" />
                </svg>
            </div>
            <h3 class="text-base font-bold text-slate-800 mb-1">Root Folder Belum Dikonfigurasi</h3>
            <p class="text-sm text-slate-400 max-w-md mx-auto mb-6">Silakan pilih Root Folder terlebih dahulu di pengaturan Google</p>
            <x-button variant="primary"
                      onclick="window.location.href = '{{ route('auth.google.status') }}'"
                      icon='<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A9 9 0 0112 3v0a9 9 0 019 9v.75M2.25 12.75A2.25 2.25 0 004.5 15h15a2.25 2.25 0 002.25-2.25M2.25 12.75H21" />'>
                Pengaturan Google
            </x-button>
        </div>
    @endif

    {{-- ===== Content Table ===== --}}
    @if ($folderId && !$error)
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden w-full">

        {{-- Empty State --}}
        @if (empty($folders) && empty($files))
            <div class="bg-white rounded-2xl p-12 text-center w-full">
                <div class="w-16 h-16 bg-blue-50 text-pln-blue rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 opacity-80" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1">Folder ini masih kosong</h3>
                <p class="text-sm text-slate-400 max-w-md mx-auto mb-6">Upload dokumen atau gunakan Auto Generate Folder untuk mengisi folder ini secara otomatis.</p>
                <x-button variant="primary"
                          icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />'
                          onclick="openModal('modal-upload')">
                    Upload File
                </x-button>
            </div>

        @else
            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr style="border-bottom:1px solid #f1f5f9; background:#fafafa;">
                            <th class="text-left px-5 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Nama Item</th>
                            <th class="text-left px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider hidden md:table-cell">Tipe</th>
                            <th class="text-left px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider hidden md:table-cell">Ukuran</th>
                            <th class="text-left px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider hidden lg:table-cell">Terakhir Diubah</th>
                            <th class="text-right px-5 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">

                        {{-- Folders --}}
                        @foreach ($folders as $folder)
                        <tr class="hover:bg-blue-50/30 transition-colors duration-150 group">
                            <td class="px-5 py-3.5">
                                <a href="{{ route('drive.show', $folder['id']) }}" class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-amber-50 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-amber-100 group-hover:scale-105 transition-all">
                                        <svg class="w-4.5 h-4.5 text-amber-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M19.5 21a3 3 0 003-3v-4.5a3 3 0 00-3-3h-15a3 3 0 00-3 3V18a3 3 0 003 3h15zM1.5 10.146V6a3 3 0 013-3h5.379a2.25 2.25 0 011.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 013 3v1.146A4.483 4.483 0 0019.5 9h-15a4.483 4.483 0 00-3 1.146z"/>
                                        </svg>
                                    </div>
                                    <span class="text-sm font-semibold text-slate-700 group-hover:text-pln-blue transition-colors">{{ $folder['name'] }}</span>
                                </a>
                            </td>
                            <td class="px-4 py-3.5 hidden md:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-50 text-amber-700">Folder</span>
                            </td>
                            <td class="px-4 py-3.5 hidden md:table-cell">
                                <span class="text-xs text-slate-400">—</span>
                            </td>
                            <td class="px-4 py-3.5 hidden lg:table-cell">
                                <span class="text-xs text-slate-400">
                                    {{ $folder['modifiedTime'] ? \Carbon\Carbon::parse($folder['modifiedTime'])->locale('id')->diffForHumans() : '—' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('drive.show', $folder['id']) }}" title="Buka Folder"
                                       class="w-8 h-8 flex items-center justify-center rounded-xl text-slate-400 hover:text-pln-blue hover:bg-blue-50/80 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </a>
                                    <button type="button" title="Ganti Nama"
                                            onclick="openRenameModal('{{ $folder['id'] }}', '{{ addslashes($folder['name']) }}')"
                                            class="w-8 h-8 flex items-center justify-center rounded-xl text-slate-400 hover:text-amber-600 hover:bg-amber-50/80 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                    <button type="button" title="Hapus"
                                            onclick="openDeleteModal('{{ $folder['id'] }}')"
                                            class="w-8 h-8 flex items-center justify-center rounded-xl text-slate-400 hover:text-red-600 hover:bg-red-50/80 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach

                        {{-- Files --}}
                        @foreach ($files as $file)
                        <tr class="hover:bg-blue-50/30 transition-colors duration-150 group">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-blue-100 group-hover:scale-105 transition-all">
                                        <svg class="w-4.5 h-4.5 text-pln-blue" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                    </div>
                                    <button type="button"
                                            onclick="openPreviewModal('{{ $file['id'] }}', '{{ addslashes($file['name']) }}', '{{ $file['mimeType'] }}', '{{ $file['webViewLink'] }}')"
                                            class="text-sm font-semibold text-slate-700 hover:text-pln-blue transition-colors text-left focus:outline-none">
                                        {{ $file['name'] }}
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-500">{{ strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'FILE' }}</span>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                <span class="text-xs text-slate-400">
                                    @if($file['size'] > 0)
                                        {{ number_format($file['size'] / 1024, 1) }} KB
                                    @else
                                        —
                                    @endif
                                </span>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <span class="text-xs text-slate-400">
                                    {{ $file['modifiedTime'] ? \Carbon\Carbon::parse($file['modifiedTime'])->locale('id')->diffForHumans() : '—' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button"
                                            title="Preview"
                                            onclick="openPreviewModal('{{ $file['id'] }}', '{{ addslashes($file['name']) }}', '{{ $file['mimeType'] }}', '{{ $file['webViewLink'] }}')"
                                            class="w-8 h-8 flex items-center justify-center rounded-xl text-slate-400 hover:text-pln-blue hover:bg-blue-50/80 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </button>
                                    <a href="{{ route('drive.file.download', ['fileId' => $file['id'], 'fileName' => $file['name']]) }}"
                                       title="Download"
                                       class="w-8 h-8 flex items-center justify-center rounded-xl text-slate-400 hover:text-pln-blue hover:bg-blue-50/80 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                        </svg>
                                    </a>
                                    <button type="button" title="Ganti Nama"
                                             onclick="openRenameModal('{{ $file['id'] }}', '{{ addslashes($file['name']) }}')"
                                             class="w-8 h-8 flex items-center justify-center rounded-xl text-slate-400 hover:text-amber-600 hover:bg-amber-50/80 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                    <button type="button" title="Hapus"
                                             onclick="openDeleteModal('{{ $file['id'] }}')"
                                             class="w-8 h-8 flex items-center justify-center rounded-xl text-slate-400 hover:text-red-600 hover:bg-red-50/80 transition-all">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach

                    </tbody>
                </table>
            </div>

            {{-- Table Footer --}}
            <div class="px-5 py-3 border-t border-slate-50">
                <p class="text-xs text-slate-400">{{ count($folders) }} folder &middot; {{ count($files) }} file</p>
            </div>
        @endif

    </div>
    @endif

    </div>{{-- end data-content --}}

    {{-- Modal: Upload File --}}
    @if ($folderId)
    <x-modal id="modal-upload" title="Upload File">
        <form action="{{ route('drive.file.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="folder_id" value="{{ $folderId }}">

            <div class="mb-4">
                <label for="file-upload" class="block text-sm font-medium text-slate-700 mb-2">Pilih File</label>
                <input type="file"
                       id="file-upload"
                       name="file"
                       required
                       class="block w-full text-sm text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-pln-blue hover:file:bg-blue-100 border border-slate-200 rounded-xl cursor-pointer focus:outline-none focus:ring-2 focus:ring-pln-blue">
                <p class="mt-1.5 text-xs text-slate-400">PDF, Word, Excel, PowerPoint, gambar, ZIP — Maks. 50 MB</p>
                @if (config('demo.enabled'))
                    {{-- DEMO: catatan upload simulasi --}}
                    <x-demo-note class="mt-3">Upload hanya simulasi: isi file tidak disimpan, hanya nama dan ukurannya dicatat sementara. Batas ukuran pada versi demo: {{ rtrim(rtrim(number_format(config('demo.upload_max_kb') / 1024, 1), '0'), '.') }} MB.</x-demo-note>
                @endif
            </div>

            @if (isset($folderStatus))
                <div class="mb-5 p-4 bg-slate-50 rounded-xl border border-slate-100">
                    @if ($folderStatus === 'complete')
                        <label class="block text-sm font-semibold text-slate-700 mb-2.5">Tipe Upload Dokumen</label>
                        <div class="space-y-3">
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="radio" name="upload_treatment" value="revision" checked class="mt-1 text-pln-blue focus:ring-pln-blue border-slate-300">
                                <div>
                                    <p class="text-xs font-semibold text-slate-700">Revisi Dokumen</p>
                                    <p class="text-[10px] text-slate-400">Ganti berkas atau perbaikan minor (status tetap Complete)</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="radio" name="upload_treatment" value="addition" class="mt-1 text-pln-blue focus:ring-pln-blue border-slate-300">
                                <div>
                                    <p class="text-xs font-semibold text-slate-700">Tambahan Dokumen</p>
                                    <p class="text-[10px] text-slate-400">Menambahkan dokumen baru (status kembali ke Pending Finalisasi)</p>
                                </div>
                            </label>
                        </div>
                    @else
                        <label class="block text-sm font-semibold text-slate-700 mb-2.5">Opsi Finalisasi</label>
                        <div class="space-y-3">
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="radio" name="upload_status" value="pending" checked class="mt-1 text-pln-blue focus:ring-pln-blue border-slate-300">
                                <div>
                                    <p class="text-xs font-semibold text-slate-700">Masih akan upload dokumen lagi</p>
                                    <p class="text-[10px] text-slate-400">Simpan status sebagai draft Pending Finalisasi</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="radio" name="upload_status" value="finalize" class="mt-1 text-pln-blue focus:ring-pln-blue border-slate-300">
                                <div>
                                    <p class="text-xs font-semibold text-slate-700">Ini upload terakhir / Finalisasi Upload</p>
                                    <p class="text-[10px] text-slate-400">Ubah status folder ini menjadi Complete</p>
                                </div>
                            </label>
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex gap-2 justify-end">
                <x-button variant="secondary" type="button" onclick="closeModal('modal-upload')">Batal</x-button>
                <x-button variant="primary" type="submit"
                          icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />'>
                    Upload
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Modal: Create Folder --}}
    <x-modal id="modal-create-folder" title="Buat Folder Baru" size="md">
        <form action="{{ route('drive.folder.create') }}" method="POST">
            @csrf
            <input type="hidden" name="folder_id" value="{{ $folderId }}">

            <div class="mb-4">
                <label for="folder-name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Folder</label>
                <div class="relative">
                    <input type="text"
                           id="folder-name"
                           name="folder_name"
                           required
                           autocomplete="off"
                           placeholder="Ketik nama folder atau pilih rekomendasi..."
                           class="block w-full px-4 py-2.5 text-sm text-slate-800 bg-slate-50/80 border border-slate-200/80 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-pln-blue/20 focus:border-pln-blue transition-all font-medium">
                </div>

                {{-- Hint / Duplicate Banner --}}
                <div id="folder-hint" class="mt-2 text-xs text-slate-400 font-medium">
                    Gunakan nama resmi sesuai struktur organisasi PLN S2JB
                </div>
            </div>

            {{-- Embedded Suggestions Panel --}}
            <div id="folder-suggestions-container" class="mb-5">
                <div class="flex items-center justify-between mb-2 px-0.5">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Rekomendasi Folder (Context-Aware)</span>
                    <span id="suggestions-count-badge" class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">Memuat...</span>
                </div>
                <div id="folder-suggestions-list" class="max-h-52 overflow-y-auto space-y-1 bg-slate-50/70 border border-slate-100 rounded-xl p-1.5">
                    <div class="text-center py-4 text-xs text-slate-400">Klik kolom input untuk memuat rekomendasi...</div>
                </div>
            </div>

            @if (config('demo.enabled'))
                {{-- DEMO: catatan buat folder simulasi --}}
                <x-demo-note class="mb-4">Folder baru hanya tersimpan sementara di data demo dan tidak menyentuh Google Drive sungguhan.</x-demo-note>
            @endif

            <div class="flex gap-2 justify-end pt-3 border-t border-slate-100">
                <x-button variant="secondary" type="button" onclick="closeModal('modal-create-folder')">Batal</x-button>
                <x-button variant="primary" type="submit" id="btn-submit-create-folder"
                          icon='<path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />'>
                    Buat Folder
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Modal: Auto Generate Folder (Context-Aware) --}}
    <x-modal id="modal-auto-generate" title="Auto Generate Folder (Context-Aware)" size="lg">
        <form id="form-auto-generate" action="{{ route('drive.folder.generate') }}" method="POST" onsubmit="handleGenerateFolderSubmit(event)">
            @csrf
            <input type="hidden" id="generate-folder-id" name="folder_id" value="{{ $folderId ?? '' }}">

            @if (config('demo.enabled'))
                {{-- DEMO: catatan simulasi Auto Generate --}}
                <x-demo-note class="mb-4">Simulasi pemindaian — tidak membaca Google Drive sungguhan. Folder baru hanya dibuat pada data demo sementara.</x-demo-note>
            @endif

            {{-- 1. Loading Preview Spinner --}}
            <div id="generate-loading-state" class="py-12 flex flex-col items-center justify-center">
                <svg class="animate-spin h-8 w-8 text-pln-blue mb-3" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-xs font-semibold text-slate-600">Mendeteksi posisi folder & memuat preview...</p>
            </div>

            {{-- 2. Notice State (CASE 1 / CASE 4) --}}
            <div id="generate-notice-state" class="hidden mb-4 p-4 bg-amber-50/90 border border-amber-200 rounded-2xl flex items-start gap-3">
                <div class="w-8 h-8 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0 mt-0.5 text-amber-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-amber-900 mb-1">Informasi Lokasi Folder</h4>
                    <p id="generate-notice-text" class="text-xs text-amber-700 leading-relaxed"></p>
                </div>
            </div>

            {{-- 3. Preview Container (CASE 2 / CASE 3) --}}
            <div id="generate-preview-container" class="hidden">
                {{-- Location Header --}}
                <div class="mb-4 bg-slate-50/90 p-4 rounded-2xl border border-slate-200/80 flex items-center justify-between flex-wrap gap-3">
                    <div class="flex flex-col gap-1 min-w-[200px]">
                        <span class="text-[10px] font-extrabold tracking-wider uppercase text-slate-400">Lokasi Target</span>
                        <span id="preview-location-path" class="text-sm font-bold text-slate-800 leading-snug">...</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span id="preview-total-badge" class="px-3 py-1.5 bg-blue-100/80 text-pln-blue text-xs font-bold rounded-xl">Total: 0</span>
                        <span id="preview-new-badge" class="px-3 py-1.5 bg-emerald-100/80 text-emerald-800 text-xs font-bold rounded-xl">0 Baru</span>
                        <span id="preview-exist-badge" class="px-3 py-1.5 bg-slate-200/80 text-slate-700 text-xs font-bold rounded-xl">0 Sudah Ada</span>
                    </div>
                </div>

                {{-- Scrollable Items Preview List --}}
                <div class="mb-4">
                    <p class="text-xs font-semibold text-slate-500 mb-2">Daftar Folder Yang Akan Diproses:</p>
                    <div id="preview-items-list" class="max-h-60 overflow-y-auto space-y-1.5 pr-1"></div>
                </div>
            </div>

            {{-- 4. Realtime Progress Bar Container --}}
            <div id="generate-progress-container" class="hidden py-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-700 flex items-center gap-2">
                        <svg id="generate-spinner" class="animate-spin h-4 w-4 text-pln-blue" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span id="generate-status-title">Membuat Struktur Folder...</span>
                    </span>
                    <span id="generate-progress-pct" class="text-xs font-extrabold text-pln-blue">0%</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden mb-3">
                    <div id="generate-progress-bar" class="bg-pln-blue h-2.5 rounded-full transition-all duration-300 w-0"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 font-medium">
                    <span id="generate-current-folder" class="truncate max-w-[280px]">Memulai...</span>
                    <span id="generate-count-label" class="shrink-0">0 / 0 folder</span>
                </div>
            </div>

            {{-- 5. Completion Result Summary Container --}}
            <div id="generate-result-container" class="hidden py-4">
                <div class="bg-emerald-50 border border-emerald-200/80 rounded-2xl p-4 mb-4">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500 text-white flex items-center justify-center text-lg font-bold shrink-0">✓</div>
                        <div>
                            <h4 class="text-sm font-bold text-emerald-950">Auto Generate Folder Berhasil!</h4>
                            <p class="text-xs text-emerald-700">Seluruh struktur folder telah disinkronkan dengan Google Drive.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                        <div class="bg-white p-2.5 rounded-xl border border-emerald-100 shadow-sm">
                            <span class="text-[10px] text-slate-400 block font-medium">Folder Baru</span>
                            <span id="result-new-count" class="text-sm font-extrabold text-emerald-600">0</span>
                        </div>
                        <div class="bg-white p-2.5 rounded-xl border border-emerald-100 shadow-sm">
                            <span class="text-[10px] text-slate-400 block font-medium">Folder Sudah Ada</span>
                            <span id="result-existing-count" class="text-sm font-extrabold text-slate-600">0</span>
                        </div>
                        <div class="bg-white p-2.5 rounded-xl border border-emerald-100 shadow-sm">
                            <span class="text-[10px] text-slate-400 block font-medium">Total Diproses</span>
                            <span id="result-total-count" class="text-sm font-extrabold text-slate-800">0</span>
                        </div>
                        <div class="bg-white p-2.5 rounded-xl border border-emerald-100 shadow-sm">
                            <span class="text-[10px] text-slate-400 block font-medium">Durasi</span>
                            <span id="result-duration" class="text-sm font-extrabold text-pln-blue">0s</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 justify-end">
                <x-button variant="secondary" type="button" id="btn-cancel-generate" onclick="closeModal('modal-auto-generate')">Tutup</x-button>
                <x-button variant="primary" type="submit" id="btn-submit-generate"
                          icon='<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />'>
                    Mulai Generate
                </x-button>
            </div>      </div>
        </form>
    </x-modal>

    {{-- Modal: Rename Item --}}
    <x-modal id="modal-rename-item" title="Ganti Nama">
        <form action="{{ route('drive.item.rename') }}" method="POST">
            @csrf
            <input type="hidden" name="folder_id" value="{{ $folderId }}">
            <input type="hidden" name="item_id" id="rename-item-id" value="">

            <div class="mb-4">
                <label for="rename-item-name" class="block text-sm font-medium text-slate-700 mb-2">Nama Baru</label>
                <input type="text"
                       id="rename-item-name"
                       name="new_name"
                       required
                       class="block w-full px-4 py-2.5 text-sm text-slate-700 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pln-blue focus:border-pln-blue transition-colors">
            </div>

            <div class="flex gap-2 justify-end">
                <x-button variant="secondary" type="button" onclick="closeModal('modal-rename-item')">Batal</x-button>
                <x-button variant="primary" type="submit">
                    Simpan Perubahan
                </x-button>
            </div>
        </form>
    </x-modal>

    {{-- Modal: Delete Item --}}
    <x-modal id="modal-delete-item" title="Konfirmasi Hapus">
        <form action="{{ route('drive.item.delete') }}" method="POST">
            @csrf
            <input type="hidden" name="folder_id" value="{{ $folderId }}">
            <input type="hidden" name="item_id" id="delete-item-id" value="">

            <div class="mb-6">
                <p class="text-sm text-slate-600">Apakah Anda yakin ingin menghapus item ini? Tindakan ini tidak dapat dibatalkan dan item akan dihapus dari Google Drive.</p>
                @if (config('demo.enabled'))
                    {{-- DEMO: catatan hapus simulasi --}}
                    <x-demo-note class="mt-3">Penghapusan hanya memengaruhi data simulasi sementara; tidak ada yang dihapus dari Google Drive sungguhan.</x-demo-note>
                @endif
            </div>

            <div class="flex gap-2 justify-end">
                <x-button variant="secondary" type="button" onclick="closeModal('modal-delete-item')">Batal</x-button>
                <x-button variant="danger" type="submit">
                    Ya, Hapus
                </x-button>
            </div>
        </form>
    </x-modal>


    {{-- Modal: Preview File --}}
    <x-modal id="modal-preview" title="Preview File" size="xl">
        <div class="flex flex-col h-full justify-between">
            <div id="preview-content" class="flex-1 flex flex-col items-center justify-center min-h-[450px] w-full overflow-y-auto bg-slate-50/30 rounded-xl p-2 border border-slate-100/50">
                <!-- Elemen preview (img/iframe/video/audio) akan diinjeksi via JS di sini -->
            </div>
            
            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100 flex-shrink-0 mt-4">
                <x-button id="preview-download-btn" href="#" variant="primary" icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />'>
                    Download
                </x-button>
                <x-button id="preview-drive-btn" href="#" target="_blank" variant="secondary" icon='<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />'>
                    {{-- DEMO: label tombol pada mode demo (membuka halaman pratinjau placeholder) --}}
                    {{ config('demo.enabled') ? 'Buka pratinjau demo' : 'Buka di Google Drive' }}
                </x-button>
                <x-button type="button" onclick="closeModal('modal-preview')" variant="secondary">
                    Tutup
                </x-button>
            </div>
        </div>
    </x-modal>

    <script>
        function toggleStatusDropdown() {
            const menu = document.getElementById('status-dropdown-menu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }

        document.addEventListener('click', function(event) {
            const wrapper = document.getElementById('status-dropdown-wrapper');
            const menu = document.getElementById('status-dropdown-menu');
            if (wrapper && menu && !wrapper.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });

        function openRenameModal(itemId, currentName) {
            document.getElementById('rename-item-id').value = itemId;
            document.getElementById('rename-item-name').value = currentName;
            openModal('modal-rename-item');
        }

        function openDeleteModal(itemId) {
            document.getElementById('delete-item-id').value = itemId;
            openModal('modal-delete-item');
        }

        function getFileExtension(filename) {
            return filename.split('.').pop().toLowerCase();
        }

        function openPreviewModal(fileId, fileName, mimeType, webViewLink) {
            // Update title
            const titleElem = document.getElementById('modal-preview-title');
            if (titleElem) titleElem.textContent = fileName;

            // Update buttons
            const downloadBtn = document.getElementById('preview-download-btn');
            const driveBtn = document.getElementById('preview-drive-btn');
            
            // Build Laravel download route dynamically
            const downloadUrl = `/drive/file/${fileId}/download/${encodeURIComponent(fileName)}`;
            downloadBtn.setAttribute('href', downloadUrl);

            if (webViewLink) {
                driveBtn.setAttribute('href', webViewLink);
                driveBtn.style.display = 'inline-flex';
            } else {
                driveBtn.style.display = 'none';
            }

            const previewContent = document.getElementById('preview-content');
            previewContent.innerHTML = '';

            @if (config('demo.enabled'))
            // DEMO: preview memakai halaman placeholder internal SIMDOK (bukan drive.google.com)
            {
                const demoFrame = document.createElement('iframe');
                demoFrame.src = `/drive/file/${encodeURIComponent(fileId)}/preview-demo`;
                demoFrame.className = 'w-full h-[65vh] border-0 rounded-xl bg-white';
                previewContent.appendChild(demoFrame);
                openModal('modal-preview');
                return;
            }
            @endif

            // Inject spinner
            const spinner = document.createElement('div');
            spinner.className = 'flex flex-col items-center justify-center py-16 text-slate-500 w-full';
            spinner.innerHTML = `
                <svg class="animate-spin h-8 w-8 text-pln-blue mb-3" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-xs text-slate-400 font-medium">Memuat preview...</span>
            `;
            previewContent.appendChild(spinner);

            // Timeout flag
            let isLoaded = false;
            const loadTimeout = setTimeout(() => {
                if (!isLoaded && previewContent.contains(spinner)) {
                    showPreviewError(previewContent);
                }
            }, 10000);

            const clearSpinnerAndTimeout = () => {
                isLoaded = true;
                clearTimeout(loadTimeout);
                if (previewContent.contains(spinner)) {
                    spinner.remove();
                }
            };

            const ext = getFileExtension(fileName);

            // Types mapping
            const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const pdfExts = ['pdf'];
            const officeExts = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
            const videoExts = ['mp4', 'webm', 'ogg', 'mkv', 'mov', 'avi'];
            const audioExts = ['mp3', 'wav', 'ogg', 'aac', 'm4a'];

            if (imageExts.includes(ext) || mimeType.startsWith('image/')) {
                const img = document.createElement('img');
                img.className = 'max-w-full max-h-[65vh] object-contain rounded-xl shadow-md border border-slate-100 bg-white p-1';
                // Try direct content link first
                img.src = `https://drive.google.com/uc?export=view&id=${fileId}`;
                img.onload = () => {
                    clearSpinnerAndTimeout();
                    previewContent.appendChild(img);
                };
                img.onerror = () => {
                    // Fallback to thumbnail
                    img.src = `https://drive.google.com/thumbnail?id=${fileId}&sz=w1200`;
                    img.onload = () => {
                        clearSpinnerAndTimeout();
                        previewContent.appendChild(img);
                    };
                    img.onerror = () => {
                        // Fallback to iframe
                        const iframe = document.createElement('iframe');
                        iframe.src = `https://drive.google.com/file/d/${fileId}/preview`;
                        iframe.className = 'w-full h-[65vh] border-0 rounded-xl bg-white';
                        iframe.onload = () => {
                            clearSpinnerAndTimeout();
                        };
                        previewContent.innerHTML = '';
                        previewContent.appendChild(iframe);
                    };
                };
            } else if (pdfExts.includes(ext) || mimeType === 'application/pdf') {
                const iframe = document.createElement('iframe');
                iframe.src = `https://drive.google.com/file/d/${fileId}/preview`;
                iframe.className = 'w-full h-[65vh] border-0 rounded-xl bg-white';
                iframe.onload = () => {
                    clearSpinnerAndTimeout();
                };
                previewContent.appendChild(iframe);
            } else if (officeExts.includes(ext) || mimeType.includes('vnd.openxmlformats-officedocument') || mimeType.includes('ms-word') || mimeType.includes('ms-excel') || mimeType.includes('ms-powerpoint')) {
                const iframe = document.createElement('iframe');
                iframe.src = `https://drive.google.com/file/d/${fileId}/preview`;
                iframe.className = 'w-full h-[65vh] border-0 rounded-xl bg-white';
                iframe.onload = () => {
                    clearSpinnerAndTimeout();
                };
                previewContent.appendChild(iframe);
            } else if (videoExts.includes(ext) || mimeType.startsWith('video/')) {
                const video = document.createElement('video');
                video.className = 'w-full max-h-[60vh] rounded-xl shadow-md bg-black focus:outline-none';
                video.controls = true;
                
                const source = document.createElement('source');
                source.src = `https://drive.google.com/uc?export=download&id=${fileId}`;
                video.appendChild(source);

                video.oncanplay = () => {
                    clearSpinnerAndTimeout();
                    previewContent.appendChild(video);
                };
                video.onerror = () => {
                    // Fallback to Google Drive Iframe player
                    const iframe = document.createElement('iframe');
                    iframe.src = `https://drive.google.com/file/d/${fileId}/preview`;
                    iframe.className = 'w-full h-[60vh] border-0 rounded-xl bg-white';
                    iframe.onload = () => {
                        clearSpinnerAndTimeout();
                    };
                    previewContent.innerHTML = '';
                    previewContent.appendChild(iframe);
                };
            } else if (audioExts.includes(ext) || mimeType.startsWith('audio/')) {
                const audio = document.createElement('audio');
                audio.className = 'w-full focus:outline-none mt-4';
                audio.controls = true;

                const source = document.createElement('source');
                source.src = `https://drive.google.com/uc?export=download&id=${fileId}`;
                audio.appendChild(source);

                const audioWrapper = document.createElement('div');
                audioWrapper.className = 'flex flex-col items-center justify-center py-12 px-6 w-full max-w-md border border-slate-100 bg-slate-50/50 rounded-2xl shadow-inner';
                audioWrapper.innerHTML = `
                    <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4 text-pln-blue">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 0v11.25m0-11.25L9 12M9 12v9m0-9l-6 1.5m6-1.5V6a3 3 0 00-3-3H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-slate-700 mb-2 truncate max-w-xs text-center">${fileName}</p>
                `;
                audioWrapper.appendChild(audio);

                audio.oncanplay = () => {
                    clearSpinnerAndTimeout();
                    previewContent.appendChild(audioWrapper);
                };
                audio.onerror = () => {
                    // Fallback to iframe
                    const iframe = document.createElement('iframe');
                    iframe.src = `https://drive.google.com/file/d/${fileId}/preview`;
                    iframe.className = 'w-full h-[60vh] border-0 rounded-xl bg-white';
                    iframe.onload = () => {
                        clearSpinnerAndTimeout();
                    };
                    previewContent.innerHTML = '';
                    previewContent.appendChild(iframe);
                };
            } else {
                // Unsupported File Types
                clearSpinnerAndTimeout();
                showUnsupportedState(previewContent);
            }

            openModal('modal-preview');
        }

        function showUnsupportedState(container) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-12 text-center w-full">
                    <div class="w-14 h-14 bg-slate-50 rounded-2xl flex items-center justify-center mb-4 border border-slate-100 shadow-sm">
                        <svg class="w-7 h-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-700 mb-1">Preview tidak tersedia untuk tipe file ini.</h3>
                    <p class="text-xs text-slate-400">Silakan unduh file untuk melihat isinya secara lengkap.</p>
                </div>
            `;
        }

        function showPreviewError(container) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-12 text-center w-full">
                    <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center mb-4 border border-red-100/50 shadow-sm">
                        <svg class="w-7 h-7 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-slate-700 mb-1">Gagal memuat preview file.</h3>
                    <p class="text-xs text-slate-400">Koneksi lambat atau file tidak dapat ditampilkan.</p>
                </div>
            `;
        }

        // Global ESC Close Keypress
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('modal-preview');
                if (modal && !modal.classList.contains('hidden')) {
                    closeModal('modal-preview');
                }
            }
        });

        // MutationObserver to clean up preview frame/elements on modal hidden
        document.addEventListener('DOMContentLoaded', function() {
            const modalPreview = document.getElementById('modal-preview');
            if (modalPreview) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class') {
                            const isHidden = modalPreview.classList.contains('hidden');
                            if (isHidden) {
                                document.getElementById('preview-content').innerHTML = '';
                            }
                        }
                    });
                });
                observer.observe(modalPreview, { attributes: true });
            }
        });
    </script>
    @endif

<script>
// ============================================================
// "Buat Folder Baru" — Context-Aware Recommendation
// Logic: fetch 1x saat modal dibuka → render list lokal
// Autocomplete: filter lokal tanpa request API ulang
// ============================================================
(function () {
    'use strict';

    const FOLDER_ID  = '{{ $folderId ?? "" }}';
    const input      = document.getElementById('folder-name');
    const list       = document.getElementById('folder-suggestions-list');
    const hint       = document.getElementById('folder-hint');
    const submitBtn  = document.getElementById('btn-submit-create-folder');
    const badge      = document.getElementById('suggestions-count-badge');
    const createBtn  = document.getElementById('btn-create-folder-trigger');

    if (!input || !FOLDER_ID) return;

    /** Array of plain strings: nama folder yang direkomendasikan */
    let recommendations = [];
    let isLeaf          = false;

    // ── Utilitas ──────────────────────────────────────────────

    function normalize(str) {
        return (str || '')
            .toLowerCase()
            .replace(/^[0-9]+[a-z]?\.\s*/i, '')   // hapus prefix "01. " atau "a. "
            .replace(/[^a-z0-9 ]/gi, '')            // hapus non-alfanumerik
            .replace(/\s+/g, ' ')
            .trim();
    }

    function setSubmitEnabled(enabled) {
        if (!submitBtn) return;
        submitBtn.disabled = !enabled;
        if (enabled) {
            submitBtn.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
        } else {
            submitBtn.classList.add('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
        }
    }

    function setHint(html) {
        if (hint) hint.innerHTML = html;
    }

    function setBadge(text) {
        if (badge) badge.textContent = text;
    }

    // ── Loading State ─────────────────────────────────────────

    function showLoading() {
        setBadge('Memuat...');
        if (list) {
            list.innerHTML = '<div class="text-center py-8 text-xs text-slate-400 animate-pulse font-medium">Memuat rekomendasi folder...</div>';
        }
        setSubmitEnabled(false);
    }

    // ── Fetch Recommendations (1x per modal open) ─────────────

    function loadRecommendations() {
        showLoading();

        const url = `/drive/folder/recommendations?folder_id=${encodeURIComponent(FOLDER_ID)}`;

        fetch(url)
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                console.log('[FOLDER RECOMMENDATIONS]', data);

                isLeaf          = data.is_leaf === true;
                recommendations = Array.isArray(data.recommendations) ? data.recommendations : [];

                // Update hint berdasarkan level
                const level = data.level || 'ROOT';
                if (isLeaf) {
                    setHint('<span class="text-slate-500 font-medium">Folder ini adalah level terakhir. Tidak ada subfolder yang dapat dibuat.</span>');
                } else if (level === 'ROOT') {
                    setHint('<span class="text-slate-400">Pilih atau ketik nama bulan resmi PLN (Januari–Desember)</span>');
                } else if (level === 'MONTH') {
                    setHint('<span class="text-slate-400">Pilih atau ketik nama Unit Induk / UP3 / UP2D / UP2K resmi PLN</span>');
                } else if (level === 'PARENT') {
                    setHint('<span class="text-slate-400">Pilih atau ketik nama sub-unit sesuai struktur PLN S2JB</span>');
                }

                renderList(input.value);
                validateInput();
            })
            .catch(err => {
                console.error('[FOLDER RECOMMENDATIONS ERROR]', err);
                setBadge('Error');
                if (list) {
                    list.innerHTML = '<div class="text-center py-6 text-xs text-red-500 font-semibold">Gagal mengambil struktur folder. Silakan Refresh halaman.</div>';
                }
            });
    }

    // ── Render List (local filter — no API call) ──────────────

    function renderList(query) {
        if (!list) return;

        // LEAF state: tidak ada subfolder
        if (isLeaf) {
            setBadge('Level Akhir');
            list.innerHTML = [
                '<div class="text-center py-6 px-3">',
                '<div class="inline-flex items-center gap-2 text-xs text-slate-500 font-medium bg-slate-50 border border-slate-100 rounded-xl px-4 py-3">',
                '<span class="text-base">🔒</span>',
                '<span>Tidak ada folder yang dapat dibuat lagi pada lokasi ini.<br><span class="text-slate-400">Folder ini merupakan level terakhir.</span></span>',
                '</div></div>',
            ].join('');
            setSubmitEnabled(false);
            return;
        }

        const q        = (query || '').trim().toLowerCase();
        const normQ    = normalize(q);

        const filtered = q === ''
            ? recommendations
            : recommendations.filter(name => {
                const normName = normalize(name);
                return normName.includes(normQ) || name.toLowerCase().includes(q);
            });

        // Empty state: semua folder sudah ada
        if (recommendations.length === 0) {
            setBadge('Lengkap ✓');
            list.innerHTML = [
                '<div class="text-center py-6 px-3">',
                '<div class="inline-flex items-center gap-2 text-xs text-emerald-700 font-semibold bg-emerald-50 border border-emerald-100 rounded-xl px-4 py-3">',
                '<span class="text-base">✅</span>',
                '<span>Semua folder yang direkomendasikan sudah tersedia.<br><span class="text-emerald-600 font-normal">Tidak ada folder baru yang perlu dibuat.</span></span>',
                '</div></div>',
            ].join('');
            return;
        }

        // Filtered empty state: pencarian tidak cocok
        if (filtered.length === 0) {
            setBadge('0 cocok');
            list.innerHTML = '<div class="text-center py-6 text-xs text-slate-400 font-medium">Tidak ada rekomendasi yang cocok. Ketik nama folder manual di atas.</div>';
            return;
        }

        setBadge(`${filtered.length} rekomendasi`);
        list.innerHTML = '';

        filtered.forEach(name => {
            const btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'w-full text-left px-3 py-2 rounded-xl text-xs transition-all duration-150 flex items-center justify-between gap-2 border bg-white border-slate-100 text-slate-700 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-900 font-semibold cursor-pointer group';

            // Left: icon + name (with highlight)
            const left = document.createElement('div');
            left.className = 'flex items-center gap-2.5 truncate min-w-0';

            const icon = document.createElement('span');
            icon.className = 'w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 bg-emerald-500 text-white group-hover:bg-emerald-600 transition-colors';
            icon.textContent = '+';

            const nameSpan = document.createElement('span');
            nameSpan.className = 'truncate';

            if (q) {
                const idx = name.toLowerCase().indexOf(q);
                if (idx !== -1) {
                    nameSpan.innerHTML =
                        escapeHtml(name.slice(0, idx)) +
                        `<mark class="bg-amber-100 text-amber-900 rounded px-0.5 font-bold not-italic">${escapeHtml(name.slice(idx, idx + q.length))}</mark>` +
                        escapeHtml(name.slice(idx + q.length));
                } else {
                    nameSpan.textContent = name;
                }
            } else {
                nameSpan.textContent = name;
            }

            left.appendChild(icon);
            left.appendChild(nameSpan);

            // Right: badge
            const pill = document.createElement('span');
            pill.className = 'text-[9.5px] font-bold px-2 py-0.5 rounded-md shrink-0 bg-emerald-100 text-emerald-800 group-hover:bg-emerald-200 transition-colors';
            pill.textContent = '+ Belum dibuat';

            btn.appendChild(left);
            btn.appendChild(pill);

            btn.addEventListener('click', () => {
                input.value = name;
                validateInput();
                input.focus();
            });

            list.appendChild(btn);
        });
    }

    function escapeHtml(str) {
        return (str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Validasi Input ────────────────────────────────────────

    function validateInput() {
        const val = (input.value || '').trim();

        if (!val) {
            setHint(hintDefault());
            setSubmitEnabled(false);
            return;
        }

        if (isLeaf) {
            setSubmitEnabled(false);
            return;
        }

        setSubmitEnabled(true);
        setHint('<span class="text-slate-400">Gunakan nama resmi sesuai struktur organisasi PLN S2JB</span>');
    }

    function hintDefault() {
        return '<span class="text-slate-400">Gunakan nama resmi sesuai struktur organisasi PLN S2JB</span>';
    }

    // ── Event Listeners ───────────────────────────────────────

    input.addEventListener('input', () => {
        renderList(input.value);
        validateInput();
    });

    // Saat modal dibuka: reset + fetch langsung
    if (createBtn) {
        createBtn.addEventListener('click', () => {
            input.value = '';
            setHint(hintDefault());
            recommendations = [];
            isLeaf          = false;
            loadRecommendations();
            setTimeout(() => input.focus(), 80);
        });
    }

    // Preload segera saat halaman dimuat
    loadRecommendations();
})();

// ===== Auto Generate Folder Handlers (Context-Aware) =====
let generatePollInterval = null;
const currentActiveFolderId = "{{ $folderId ?? '' }}";

function setSubmitBtnState(btn, enabled) {
    if (!btn) return;
    btn.disabled = !enabled;
    if (enabled) {
        btn.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
    } else {
        btn.classList.add('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
    }
}

function handleAutoGenerateBtnClick(event) {
    if (event) event.preventDefault();
    console.log('[AUTO-GENERATE CONTEXT-AWARE] Button Auto Generate diklik');
    openModal('modal-auto-generate');

    const loadingState = document.getElementById('generate-loading-state');
    const noticeState = document.getElementById('generate-notice-state');
    const previewContainer = document.getElementById('generate-preview-container');
    const progressContainer = document.getElementById('generate-progress-container');
    const resultContainer = document.getElementById('generate-result-container');
    const submitBtn = document.getElementById('btn-submit-generate');

    if (loadingState) loadingState.classList.remove('hidden');
    if (noticeState) noticeState.classList.add('hidden');
    if (previewContainer) previewContainer.classList.add('hidden');
    if (progressContainer) progressContainer.classList.add('hidden');
    if (resultContainer) resultContainer.classList.add('hidden');
    setSubmitBtnState(submitBtn, false);

    // Fetch Preview Data
    fetch(`/drive/folder/generate-preview?folder_id=${encodeURIComponent(currentActiveFolderId)}`)
        .then(res => res.json())
        .then(data => {
            console.log('[AUTO-GENERATE PREVIEW RESPONSE]', data);
            if (loadingState) loadingState.classList.add('hidden');

            if (!data.can_generate) {
                // CASE 1 or CASE 4: Show Notice
                if (noticeState) {
                    noticeState.classList.remove('hidden');
                    const textEl = document.getElementById('generate-notice-text');
                    if (textEl) textEl.textContent = data.message || 'Generasi folder tidak diizinkan pada lokasi ini.';
                }
                setSubmitBtnState(submitBtn, false);
            } else {
                // CASE 2 or CASE 3: Show Preview Items
                if (previewContainer) previewContainer.classList.remove('hidden');
                
                const locationPath = document.getElementById('preview-location-path');
                const totalBadge = document.getElementById('preview-total-badge');
                const newBadge = document.getElementById('preview-new-badge');
                const existBadge = document.getElementById('preview-exist-badge');
                const itemsList = document.getElementById('preview-items-list');

                if (locationPath) locationPath.textContent = data.location_path || 'Lokasi Aktif';
                if (totalBadge) totalBadge.textContent = `Total: ${data.total_folders || 0} Folder`;
                if (newBadge) newBadge.textContent = `${data.new_count || 0} Baru`;
                if (existBadge) existBadge.textContent = `${data.existing_count || 0} Sudah Ada`;

                if (itemsList) {
                    itemsList.innerHTML = '';
                    (data.items || []).forEach(item => {
                        const div = document.createElement('div');
                        div.className = `flex items-center justify-between p-2 rounded-lg text-xs border ${
                            item.exists 
                                ? 'bg-slate-50 border-slate-200 text-slate-500' 
                                : 'bg-emerald-50/70 border-emerald-200/80 text-emerald-900 font-medium'
                        }`;

                        const left = document.createElement('div');
                        left.className = 'flex items-center gap-2 truncate';
                        
                        const icon = document.createElement('span');
                        icon.className = `w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold ${
                            item.exists ? 'bg-slate-200 text-slate-600' : 'bg-emerald-600 text-white'
                        }`;
                        icon.textContent = item.exists ? '✓' : '+';

                        const nameSpan = document.createElement('span');
                        nameSpan.className = 'truncate';
                        nameSpan.textContent = item.parent_name ? `${item.parent_name} / ${item.name}` : item.name;

                        left.appendChild(icon);
                        left.appendChild(nameSpan);

                        const badge = document.createElement('span');
                        badge.className = `text-[10px] font-bold px-2 py-0.5 rounded ${
                            item.exists ? 'bg-slate-200/80 text-slate-600' : 'bg-emerald-200/80 text-emerald-800'
                        }`;
                        badge.textContent = item.exists ? 'Sudah Ada' : 'Akan Dibuat';

                        div.appendChild(left);
                        div.appendChild(badge);
                        itemsList.appendChild(div);
                    });
                }

                setSubmitBtnState(submitBtn, true);
            }
        })
        .catch(err => {
            console.error('[AUTO-GENERATE PREVIEW ERROR]', err);
            if (loadingState) loadingState.classList.add('hidden');
            alert('Gagal memuat preview lokasi folder.');
        });
}

function handleGenerateFolderSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);
    formData.set('folder_id', currentActiveFolderId);

    console.log('[AUTO-GENERATE SUBMIT]', { folder_id: currentActiveFolderId });

    const submitBtn = document.getElementById('btn-submit-generate');
    const previewContainer = document.getElementById('generate-preview-container');
    const progressContainer = document.getElementById('generate-progress-container');

    setSubmitBtnState(submitBtn, false);
    if (previewContainer) previewContainer.classList.add('hidden');
    if (progressContainer) progressContainer.classList.remove('hidden');

    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('[AUTO-GENERATE SUBMIT RESPONSE]', data);
        if (data.success && data.log_id) {
            startGenerationPolling(data.log_id);
        } else {
            alert(data.message || 'Gagal memulai pembuatan folder.');
            resetGenerateModalUI();
        }
    })
    .catch(err => {
        console.error('[AUTO-GENERATE SUBMIT ERROR]', err);
        alert('Terjadi kesalahan koneksi.');
        resetGenerateModalUI();
    });
}

function startGenerationPolling(logId) {
    if (generatePollInterval) clearInterval(generatePollInterval);

    const pollStatus = () => {
        fetch(`/drive/folder/generate-status?log_id=${logId}`)
            .then(res => res.json())
            .then(data => {
                console.log('[AUTO-GENERATE POLLING]', data);
                const progressPct = document.getElementById('generate-progress-pct');
                const progressBar = document.getElementById('generate-progress-bar');
                const currentFolder = document.getElementById('generate-current-folder');
                const countLabel = document.getElementById('generate-count-label');

                const pct = Math.min(100, Math.max(0, data.progress || 0));

                if (progressPct) progressPct.textContent = `${pct}%`;
                if (progressBar) progressBar.style.width = `${pct}%`;
                if (currentFolder) currentFolder.textContent = data.current_folder || 'Memproses...';
                if (countLabel) countLabel.textContent = `${data.processed_folders || 0} / ${data.total_folders || 0} folder`;

                if (data.status === 'completed' || pct >= 100) {
                    clearInterval(generatePollInterval);

                    const progressContainer = document.getElementById('generate-progress-container');
                    const resultContainer = document.getElementById('generate-result-container');

                    if (progressContainer) progressContainer.classList.add('hidden');
                    if (resultContainer) {
                        resultContainer.classList.remove('hidden');
                        document.getElementById('result-new-count').textContent = data.new_count || 0;
                        document.getElementById('result-existing-count').textContent = data.existing_count || 0;
                        document.getElementById('result-total-count').textContent = data.total_folders || 0;
                        document.getElementById('result-duration').textContent = `${data.duration_seconds || 0}s`;
                    }

                    setTimeout(() => {
                        closeModal('modal-auto-generate');
                        window.location.reload();
                    }, 2500);
                } else if (data.status === 'failed') {
                    clearInterval(generatePollInterval);
                    alert('Gagal membuat folder: ' + (data.error_message || 'Error tidak diketahui'));
                    resetGenerateModalUI();
                }
            })
            .catch(err => console.error('[AUTO-GENERATE POLLING ERROR]', err));
    };

    pollStatus();
    generatePollInterval = setInterval(pollStatus, 1200);
}

function resetGenerateModalUI() {
    if (generatePollInterval) clearInterval(generatePollInterval);
    const submitBtn = document.getElementById('btn-submit-generate');
    const progressContainer = document.getElementById('generate-progress-container');
    setSubmitBtnState(submitBtn, true);
    if (progressContainer) progressContainer.classList.add('hidden');
}
</script>

    </div>{{-- end data-content --}}

</x-layouts.app>
