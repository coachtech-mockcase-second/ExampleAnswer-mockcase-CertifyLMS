@props([
    'align' => 'right',
])

@php
    $alignment = $align === 'left' ? 'left-0' : 'right-0';
@endphp

<div data-dropdown {{ $attributes->merge(['class' => 'relative inline-block']) }}>
    @isset($trigger)
        <div data-dropdown-trigger aria-haspopup="menu" aria-expanded="false">
            {{ $trigger }}
        </div>
    @endisset

    <div
        data-dropdown-menu
        role="menu"
        class="absolute {{ $alignment }} top-full mt-1 z-40 hidden min-w-[12rem] py-1 bg-surface-raised border border-[var(--border-subtle)] rounded-md shadow-md"
    >
        {{ $slot }}
    </div>
</div>
