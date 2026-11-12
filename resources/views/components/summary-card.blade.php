@props([
    'icon',
    'color' => 'blue',
    'title',
    'value',
    'description',
    'actionUrl' => null,
    'actionText' => 'Lihat Draft →',
    'isProgress' => false,
])

@php
    // Mapping warna
    $colorClasses = [
        'blue' => [
            'circle' => 'bg-blue-500/10 text-blue-600',
            'value' => 'text-slate-800',
        ],
        'green' => [
            'circle' => 'bg-green-500/10 text-green-600',
            'value' => 'text-green-600',
        ],
        'amber' => [
            'circle' => 'bg-amber-500/10 text-amber-600',
            'value' => 'text-amber-500',
        ],
        'red' => [
            'circle' => 'bg-red-500/10 text-red-600',
            'value' => 'text-red-500',
        ],
        'pln-blue' => [
            'circle' => 'bg-pln-blue/10 text-pln-blue',
            'value' => 'text-pln-blue',
        ],
    ][$color] ?? [
        'circle' => 'bg-blue-500/10 text-blue-600',
        'value' => 'text-slate-800',
    ];
@endphp

<div class="bg-white rounded-2xl shadow-sm p-6 transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 {{ $actionUrl ? 'cursor-pointer' : '' }}"
     @if($actionUrl) onclick="window.location.href='{{ $actionUrl }}'" @endif>
    
    {{-- Icon --}}
    <div class="w-11 h-11 rounded-full flex items-center justify-center {{ $colorClasses['circle'] }} mb-4">
        @switch($icon)
            @case('users')
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                </svg>
                @break
            @case('check-circle')
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                @break
            @case('clock')
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                @break
            @case('exclamation-triangle')
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                @break
            @case('chart-bar')
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </svg>
                @break
        @endswitch
    </div>

    {{-- Title --}}
    <h3 class="text-[13px] font-medium text-slate-600 uppercase tracking-wide mb-1">{{ $title }}</h3>

    {{-- Value --}}
    <div class="count-animate-wrapper opacity-0 transition-opacity duration-300">
        <p class="text-[36px] font-bold tracking-tight {{ $colorClasses['value'] }} leading-none mb-1.5">
            @if($isProgress)
                <span data-count="{{ $value }}" data-percent="true">0%</span>
            @else
                <span data-count="{{ $value }}">0</span>
            @endif
        </p>
    </div>

    {{-- Description --}}
    <p class="text-[13px] text-slate-400 leading-normal">{!! $description !!}</p>

    {{-- Optional Action --}}
    @if($actionUrl)
        <div class="mt-4 pt-1">
            <a href="{{ $actionUrl }}" 
               onclick="event.stopPropagation();"
               class="inline-flex items-center gap-1 px-4 py-1.5 text-xs font-semibold text-amber-600 bg-amber-500/10 hover:bg-amber-600 hover:text-white rounded-full transition-all duration-200 cursor-pointer">
                {!! $actionText !!}
            </a>
        </div>
    @endif

    {{-- Progress Bar (Progress Card Only) --}}
    @if($isProgress)
        <div class="mt-4">
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden w-full">
                <div class="h-full bg-pln-blue rounded-full transition-all duration-700 ease-out"
                     style="width: 0%"
                     data-progress="{{ $value }}"></div>
            </div>
        </div>
    @endif

</div>
