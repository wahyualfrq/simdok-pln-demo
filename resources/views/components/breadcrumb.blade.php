@props([
    'items' => [], // array of ['id' => '', 'name' => '']
])

@if (!empty($items))
<nav aria-label="Breadcrumb" class="flex items-center gap-1.5 text-xs sm:text-sm">
    <a href="{{ route('drive.index') }}"
       class="flex items-center gap-1.5 text-slate-400 hover:text-pln-blue font-medium transition-colors duration-150 shrink-0">
        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
        </svg>
        <span>Drive</span>
    </a>

    @foreach ($items as $index => $item)
        <svg class="w-3.5 h-3.5 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>

        @if ($loop->last)
            <span class="font-bold text-slate-800 truncate max-w-[240px] sm:max-w-xs md:max-w-md" title="{{ $item['name'] }}">
                {{ $item['name'] }}
            </span>
        @else
            <a href="{{ route('drive.show', $item['id']) }}"
               class="text-slate-400 hover:text-pln-blue font-medium transition-colors duration-150 truncate max-w-[180px] sm:max-w-xs"
               title="{{ $item['name'] }}">
                {{ $item['name'] }}
            </a>
        @endif
    @endforeach
</nav>
@endif
