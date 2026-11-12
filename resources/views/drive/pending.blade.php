<x-layouts.app title="Pending Finalisasi" :active-menu="'dashboard'">

    {{-- ===== Page Header ===== --}}
    <div class="mb-6">
        <h2 class="text-xl font-bold text-slate-800">Folder Pending Finalisasi</h2>
        <p class="text-sm text-slate-400 mt-0.5">Daftar folder yang sudah ada file di Drive namun belum dicentang di Google Sheet.</p>
    </div>

    {{-- ===== Content Table ===== --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        @if (empty($pendingFolders))
            <div class="text-center py-16">
                <div class="w-14 h-14 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-slate-100 shadow-sm">
                    <svg class="w-7 h-7 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-slate-600 mb-1">Semua folder aman</h3>
                <p class="text-xs text-slate-400">Tidak ada folder yang sedang berada dalam status Pending Finalisasi.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr style="border-bottom:1px solid #f1f5f9; background:#fafafa;">
                            <th class="text-left px-5 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Semester</th>
                            <th class="text-left px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Bulan</th>
                            <th class="text-left px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Region</th>
                            <th class="text-left px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Unit</th>
                            <th class="text-center px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">File di Drive</th>
                            <th class="text-left px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Terakhir Sync</th>
                            <th class="text-center px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                            <th class="text-right px-5 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach ($pendingFolders as $folder)
                        <tr class="hover:bg-slate-50/70 transition-colors duration-100 group">
                            <td class="px-5 py-3 text-sm text-slate-600 font-medium whitespace-nowrap">{{ $folder['semester'] }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $folder['bulan'] }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700 font-semibold whitespace-nowrap">{{ $folder['region'] }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $folder['unit'] }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600">
                                    {{ $folder['evidence_count'] }} file
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                                {{ $folder['last_sync'] ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Pending Finalisasi
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end">
                                    @if ($folder['folder_id'])
                                        <x-button variant="secondary" size="sm" href="{{ route('drive.show', $folder['folder_id']) }}"
                                                  icon='<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75h16.5m-16.5 0a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25h16.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25m-16.5 0V6.75A2.25 2.25 0 016 4.5h3.75a2.25 2.25 0 011.696.772l1.608 1.978a.75.75 0 00.566.25h5.88a2.25 2.25 0 012.25 2.25v.75" />'>
                                            Buka Folder
                                        </x-button>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t border-slate-50">
                <p class="text-xs text-slate-400">{{ count($pendingFolders) }} folder menunggu finalisasi</p>
            </div>
        @endif
    </div>

</x-layouts.app>
