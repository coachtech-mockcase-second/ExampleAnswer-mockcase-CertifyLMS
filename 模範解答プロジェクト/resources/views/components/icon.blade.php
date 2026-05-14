@props([
    'name',
    'variant' => 'outline',
])

@php
    $prefix = match ($variant) {
        'solid' => 'heroicon-s',
        'mini' => 'heroicon-m',
        default => 'heroicon-o',
    };
    $hasAriaLabel = $attributes->has('aria-label') || $attributes->has('aria-labelledby');
    $attrs = $hasAriaLabel ? $attributes : $attributes->merge(['aria-hidden' => 'true']);
    $class = $attrs->get('class', '');
    $otherAttrs = $attrs->except('class')->getAttributes();
@endphp

{!! svg("{$prefix}-{$name}", $class, $otherAttrs)->toHtml() !!}
