@props([
    'variant' => 'gray',
    'size' => 'md',
])

@php
    $variants = [
        'success' => 'bg-success-50 text-success-800 border border-success-200',
        'warning' => 'bg-warning-50 text-warning-800 border border-warning-200',
        'danger' => 'bg-danger-50 text-danger-800 border border-danger-200',
        'info' => 'bg-info-50 text-info-800 border border-info-200',
        'primary' => 'bg-primary-50 text-primary-800 border border-primary-200',
        'secondary' => 'bg-secondary-50 text-secondary-800 border border-secondary-200',
        'gray' => 'bg-ink-50 text-ink-700 border border-ink-200',
    ];

    $sizes = [
        'sm' => 'text-[10px] px-2 py-0.5',
        'md' => 'text-xs px-2.5 py-1',
    ];

    $classes = 'inline-flex items-center gap-1 rounded-full font-semibold ' . ($variants[$variant] ?? $variants['gray']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
