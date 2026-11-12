@props(['id' => 'modal', 'title' => 'Modal', 'size' => 'md', 'overflowVisible' => false])

<div id="{{ $id }}"
     class="fixed inset-0 z-50 hidden"
     role="dialog"
     aria-modal="true"
     aria-labelledby="{{ $id }}-title">

    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px] transition-opacity"
         onclick="closeModal('{{ $id }}')"></div>

    {{-- Modal Container --}}
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-xl border border-slate-100 w-full
            {{ $size === 'sm' ? 'max-w-sm' : '' }}
            {{ $size === 'md' ? 'max-w-md' : '' }}
            {{ $size === 'lg' ? 'max-w-3xl' : '' }}
            {{ $size === 'xl' ? 'max-w-5xl' : '' }}
            transform transition-all {{ $overflowVisible ? 'overflow-visible' : 'overflow-hidden' }} flex flex-col max-h-[90vh]">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-1 h-4 rounded-full bg-pln-blue"></div>
                    <h3 id="{{ $id }}-title" class="text-[15px] font-semibold text-slate-800">{{ $title }}</h3>
                </div>
                <button onclick="closeModal('{{ $id }}')"
                        class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition-all duration-150">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 {{ $overflowVisible ? 'overflow-visible' : 'overflow-y-auto' }} flex-1">
                {{ $slot }}
            </div>

        </div>
    </div>
</div>

@once
<script>
function openModal(id) {
    console.log('[AUTO-GENERATE FRONTEND 2/6] Membuka modal ID: ' + id);
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    } else {
        console.error('[MODAL ERROR] Element modal ID tidak ditemukan: ' + id);
    }
}
function closeModal(id) {
    console.log('[MODAL CLOSE] Menutup modal ID: ' + id);
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
}
</script>
@endonce
