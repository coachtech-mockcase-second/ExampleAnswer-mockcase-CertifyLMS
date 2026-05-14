@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'disabled' => false,
    'loading' => false,
])

@php
    $base = 'inline-flex items-center justify-center gap-1.5 rounded-md font-semibold transition-colors duration-fast ease-out-quint focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary-500';

    $variants = [
        'primary' => 'bg-primary-600 text-white hover:bg-primary-700',
        'outline' => 'bg-transparent border border-ink-200 text-ink-900 hover:bg-surface-sunken',
        'ghost' => 'bg-transparent text-ink-700 hover:bg-ink-50',
        'danger' => 'bg-danger-600 text-white hover:bg-danger-700',
        'secondary' => 'bg-ink-100 text-ink-900 hover:bg-ink-200',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs rounded-[8px]',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base',
    ];

    $stateClasses = ($disabled || $loading) ? 'opacity-50 cursor-not-allowed' : '';

    $classes = trim("$base {$variants[$variant]} {$sizes[$size]} $stateClasses");
@endphp

<button
    type="{{ $type }}"
    @if ($disabled || $loading) disabled aria-disabled="true" @endif
    @if ($loading) aria-busy="true" @endif
    {{ $attributes->merge(['class' => $classes]) }}
>
    @if ($loading)
        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @endif
    {{ $slot }}
</button>
