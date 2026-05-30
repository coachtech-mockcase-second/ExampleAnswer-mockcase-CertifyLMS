{{--
    汎用カード。コンテンツを枠線 + 角丸 + 影で囲む共通コンテナ。
    props: padding(none/sm/md/lg)・shadow(none/sm/md/lg) + header / footer スロット。
    header・footer スロットを渡したときだけ、仕切り線つきのヘッダ / フッターを描画する。
--}}
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

<div {{ $attributes->merge(['class' => "bg-surface-raised border border-subtle rounded-2xl $cardShadow transition-colors duration-fast hover:border-primary-200 hover:shadow-md"]) }}>
    @if ($header)
        <div class="border-b border-subtle px-6 py-4 font-semibold text-ink-900">
            {{ $header }}
        </div>
    @endif

    <div class="{{ $bodyPadding }}">
        {{ $slot }}
    </div>

    @if ($footer)
        <div class="border-t border-subtle px-6 py-4 bg-surface-sunken/50">
            {{ $footer }}
        </div>
    @endif
</div>
