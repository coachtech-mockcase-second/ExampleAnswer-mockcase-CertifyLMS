@props([
    'src' => null,
    'name' => null,
    'size' => 'md',
    'alt' => null,
])

@php
    $sizes = [
        'sm' => 'w-6 h-6 text-[10px]',
        'md' => 'w-10 h-10 text-sm',
        'lg' => 'w-16 h-16 text-lg',
        'xl' => 'w-24 h-24 text-2xl',
    ];

    $sizeClass = $sizes[$size] ?? $sizes['md'];

    $initial = $name ? mb_substr($name, 0, 1) : '?';

    // 名前ハッシュから決定的にカラーを決める (背景は palette 内 6 色から選択)
    $palette = ['bg-primary-100 text-primary-800', 'bg-secondary-100 text-secondary-800', 'bg-success-100 text-success-800', 'bg-warning-100 text-warning-800', 'bg-info-100 text-info-800', 'bg-danger-100 text-danger-800'];
    $bgClass = $palette[abs(crc32($name ?? '')) % count($palette)];

    $altText = $alt ?? ($name ? "{$name} のアバター" : 'アバター');
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $altText }}" {{ $attributes->merge(['class' => "$sizeClass rounded-full object-cover"]) }}>
@else
    <span
        aria-label="{{ $altText }}"
        {{ $attributes->merge(['class' => "$sizeClass rounded-full inline-flex items-center justify-center font-semibold select-none $bgClass"]) }}
    >
        {{ $initial }}
    </span>
@endif
