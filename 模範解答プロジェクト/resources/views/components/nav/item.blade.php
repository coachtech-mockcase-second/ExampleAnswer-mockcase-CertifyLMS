@props([
    'route',
    'icon' => null,
    'label',
    'badge' => null,
    'active' => null,
])

@if (\Illuminate\Support\Facades\Route::has($route))
    @php
        $isActive = $active ?? request()->routeIs($route . '*');
        $base = 'group relative flex items-center gap-2.5 px-3 py-2 rounded-[10px] text-sm transition-colors duration-fast';
        $stateClass = $isActive
            ? 'bg-gradient-to-r from-primary-50 to-transparent text-primary-700 font-semibold'
            : 'text-ink-700 font-medium hover:bg-ink-50 hover:text-ink-900';
    @endphp

    <a
        href="{{ route($route) }}"
        @if ($isActive) aria-current="page" @endif
        class="{{ $base }} {{ $stateClass }}"
    >
        @if ($isActive)
            <span aria-hidden="true" class="absolute left-0 top-2 bottom-2 w-[3px] bg-primary-600 rounded-r-[4px]"></span>
        @endif

        @if ($icon)
            <x-icon :name="$icon" class="w-5 h-5 flex-shrink-0 {{ $isActive ? 'text-primary-600' : 'text-ink-500 group-hover:text-ink-700' }}" />
        @endif

        <span class="flex-1 truncate">{{ $label }}</span>

        @if ($badge !== null && $badge > 0)
            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 rounded-full bg-danger-500 text-white text-[10px] font-bold tnum">
                {{ $badge > 99 ? '99+' : $badge }}
            </span>
        @endif
    </a>
@endif
