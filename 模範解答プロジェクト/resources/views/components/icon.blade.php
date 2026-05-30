{{--
    アイコン。Heroicons の SVG を名前で描画する。
    props: name(kebab-case の Heroicons 名、必須)・variant(outline/solid/mini)。class でサイズ・色を指定。
    aria-label を渡さなければ装飾扱いで aria-hidden を自動付与する。
--}}
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
