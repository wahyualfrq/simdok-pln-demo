@props([
    'status' => 'incomplete', // complete, incomplete, na
    'size'   => 'md',
    'href'   => null,
])

@php
$statusConfig = match($status) {
    'complete'   => ['bg' => 'bg-green-100 text-green-700', 'dot' => 'bg-green-500', 'label' => 'Lengkap'],
    'incomplete' => ['bg' => 'bg-red-100 text-red-700',   'dot' => 'bg-red-500',   'label' => 'Belum'],
    default      => ['bg' => 'bg-slate-100 text-slate-500', 'dot' => 'bg-slate-400', 'label' => 'N/A'],
};

$sizeStyles = match($size) {
    'sm'  => 'px-2 py-0.5 text-xs gap-1',
    'lg'  => 'px-4 py-1.5 text-sm gap-2',
    default => 'px-2.5 py-1 text-xs gap-1.5',
};
@endphp

@if ($href)
    <a href="{{ $href }}"
       title="{{ $statusConfig['label'] }}"
       class="inline-flex items-center rounded-full font-medium transition-all duration-150 hover:opacity-80 {{ $statusConfig['bg'] }} {{ $sizeStyles }}">
        <span class="w-1.5 h-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
        {{ $slot ?: $statusConfig['label'] }}
    </a>
@else
    <span title="{{ $statusConfig['label'] }}"
          class="inline-flex items-center rounded-full font-medium {{ $statusConfig['bg'] }} {{ $sizeStyles }}">
        <span class="w-1.5 h-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
        {{ $slot ?: $statusConfig['label'] }}
    </span>
@endif
