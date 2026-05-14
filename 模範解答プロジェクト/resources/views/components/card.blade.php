@props([
    'padding' => 'md',
    'shadow' => 'sm',
    'header' => null,
    'footer' => null,
])

@php
    $paddings = [
        'none' => '',
        'sm' => 'p-4',
        'md' => 'p-6',
        'lg' => 'p-8',
    ];

    $shadows = [
        'none' => '',
        'sm' => 'shadow-sm',
        'md' => 'shadow-md',
        'lg' => 'shadow-lg',
    ];

    $bodyPadding = $paddings[$padding] ?? $paddings['md'];
    $cardShadow = $shadows[$shadow] ?? $shadows['sm'];
@endphp

<div {{ $attributes->merge(['class' => "bg-surface-raised border border-[var(--border-subtle)] rounded-2xl $cardShadow overflow-hidden transition-colors duration-fast hover:border-primary-200 hover:shadow-md"]) }}>
    @if ($header)
        <div class="border-b border-[var(--border-subtle)] px-6 py-4 font-semibold text-ink-900">
            {{ $header }}
        </div>
    @endif

    <div class="{{ $bodyPadding }}">
        {{ $slot }}
    </div>

    @if ($footer)
        <div class="border-t border-[var(--border-subtle)] px-6 py-4 bg-surface-sunken/50">
            {{ $footer }}
        </div>
    @endif
</div>
