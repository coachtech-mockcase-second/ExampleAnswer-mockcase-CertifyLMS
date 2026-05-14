@props([
    'type' => 'info',
    'dismissible' => false,
    'title' => null,
])

@php
    $config = [
        'success' => [
            'wrapper' => 'bg-success-50 border-success-200 text-success-800',
            'icon' => 'check-circle',
            'iconColor' => 'text-success-600',
        ],
        'error' => [
            'wrapper' => 'bg-danger-50 border-danger-200 text-danger-800',
            'icon' => 'x-circle',
            'iconColor' => 'text-danger-600',
        ],
        'info' => [
            'wrapper' => 'bg-info-50 border-info-200 text-info-800',
            'icon' => 'information-circle',
            'iconColor' => 'text-info-600',
        ],
        'warning' => [
            'wrapper' => 'bg-warning-50 border-warning-200 text-warning-800',
            'icon' => 'exclamation-triangle',
            'iconColor' => 'text-warning-700',
        ],
    ];

    $current = $config[$type] ?? $config['info'];
@endphp

<div
    role="alert"
    @if ($dismissible) data-dismissible-alert @endif
    {{ $attributes->merge(['class' => "border rounded-lg p-4 flex gap-3 transition-opacity duration-normal {$current['wrapper']}"]) }}
>
    <x-icon :name="$current['icon']" class="w-5 h-5 flex-shrink-0 {{ $current['iconColor'] }}" />

    <div class="flex-1 text-sm">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="@if ($title) mt-1 @endif">{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button type="button" data-dismiss-alert aria-label="閉じる" class="flex-shrink-0 text-current opacity-60 hover:opacity-100 transition-opacity">
            <x-icon name="x-mark" class="w-5 h-5" />
        </button>
    @endif
</div>
