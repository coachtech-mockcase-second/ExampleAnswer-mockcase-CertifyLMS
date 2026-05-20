@props([
    'icon' => 'chart-bar',
    'iconColor' => 'text-ink-500',
    'label',
    'value',
    'delta' => null,
    'deltaColor' => 'text-ink-500',
    'valueColor' => 'text-ink-900',
    'featured' => false,
])

@php
    $base = 'rounded-2xl border px-5 py-5 transition-colors duration-fast';
    $tone = $featured
        ? 'border-transparent bg-gradient-to-br from-warning-200 via-success-200 to-primary-200 text-ink-900'
        : 'border-[var(--border-subtle)] bg-surface-raised shadow-sm hover:border-primary-200 hover:shadow-md';
@endphp

<div {{ $attributes->merge(['class' => "$base $tone"]) }}>
    <div class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider {{ $featured ? 'text-ink-900/70' : 'text-ink-500' }}">
        <x-icon :name="$icon" class="w-3.5 h-3.5 {{ $featured ? 'text-ink-900/80' : $iconColor }}" />
        <span>{{ $label }}</span>
    </div>

    <div class="mt-1.5 font-display font-extrabold text-[40px] leading-none tracking-tight tabular-nums {{ $valueColor }}">
        {{ $value }}
    </div>

    @if ($delta)
        <div class="mt-1.5 text-[11px] font-semibold {{ $featured ? 'text-ink-900/65' : $deltaColor }}">
            {{ $delta }}
        </div>
    @endif

    @isset($chart)
        <div class="mt-3">{{ $chart }}</div>
    @endisset
</div>
