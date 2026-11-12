{{-- DEMO: Catatan kecil kontekstual (ikon info + satu kalimat). Hanya tampil saat config('demo.enabled'). Isi diberikan lewat slot. --}}
@if (config('demo.enabled'))
    <div role="note" {{ $attributes->merge(['class' => 'flex items-start gap-2 px-3 py-2 rounded-2xl shadow-sm bg-amber-50 text-amber-800']) }}>
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
        <p class="text-xs flex-1">{{ $slot }}</p>
    </div>
@endif
