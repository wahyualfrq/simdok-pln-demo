{{-- DEMO: Banner global "demonstrasi". Hanya tampil saat config('demo.enabled'). Borderless, rounded-2xl, shadow-sm, amber lembut (07_UI_UX_GUIDELINE). --}}
@if (config('demo.enabled'))
    <div class="px-4 sm:px-6 pt-4">
        <div role="note" class="flex items-start gap-3 p-4 rounded-2xl shadow-sm bg-amber-50 text-amber-800 w-full">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
            </svg>
            <p class="text-sm flex-1">
                <span class="font-bold">{{ config('demo.banner.title') }}</span>
                &mdash; {{ config('demo.banner.message') }}
            </p>
        </div>
    </div>
@endif
