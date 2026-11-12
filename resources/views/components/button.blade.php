@props([
    'variant'  => 'primary', // primary, secondary, danger, success, ghost, outline
    'size'     => 'md',      // sm, md, lg
    'type'     => 'button',
    'href'     => null,
    'icon'     => null,
    'disabled' => false,
])

@php
$variantStyles = match($variant) {
    'primary'   => 'bg-pln-blue text-white hover:bg-blue-700 shadow-sm shadow-blue-100/60',
    'secondary' => 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 hover:border-slate-300',
    'danger'    => 'bg-red-600 text-white hover:bg-red-700 shadow-sm shadow-red-100/60',
    'success'   => 'bg-green-600 text-white hover:bg-green-700 shadow-sm shadow-green-100/60',
    'ghost'     => 'text-slate-600 hover:bg-slate-100 hover:text-slate-800',
    'outline'   => 'border border-pln-blue text-pln-blue hover:bg-pln-blue hover:text-white',
    default     => 'bg-pln-blue text-white hover:bg-blue-700',
};

$sizeStyles = match($size) {
    'sm' => 'px-3 py-1.5 text-xs gap-1.5 rounded-lg',
    'lg' => 'px-5 py-2.5 text-sm gap-2 rounded-xl',
    default => 'px-4 py-2 text-sm gap-2 rounded-xl',
};

$baseStyles = "inline-flex items-center justify-center font-medium transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-pln-blue/30 focus:ring-offset-1 whitespace-nowrap";
$disabledStyles = $disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '';
$allStyles = implode(' ', array_filter([$baseStyles, $variantStyles, $sizeStyles, $disabledStyles]));
@endphp

@if ($href)
    <a href="{{ $href }}" class="{{ $allStyles }}" {{ $attributes }}>
        @if ($icon)
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                {!! $icon !!}
            </svg>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}"
            class="{{ $allStyles }}"
            @if($disabled) disabled @endif
            {{ $attributes }}>
        @if ($icon)
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                {!! $icon !!}
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
