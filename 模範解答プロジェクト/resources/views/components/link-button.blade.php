@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => '#',
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

    $classes = trim("$base {$variants[$variant]} {$sizes[$size]}");
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
