@props([
    'padding' => true,
    'class'   => '',
])

<div class="bg-white rounded-2xl border border-slate-100 shadow-sm {{ $padding ? 'p-6' : '' }} {{ $class }}">
    {{ $slot }}
</div>
