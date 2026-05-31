{{--
    ドロップダウン項目。<x-dropdown> の中に並べるメニュー 1 件(リンク / フォーム送信 / ボタン)。
    props: href(遷移先)・method(指定すると href へその HTTP メソッドで送信するボタンになる)・variant(default/danger)・icon(Heroicons 名) + ラベルスロット。
    href のみ=リンク / href + method=送信フォーム / どちらも無し=ただのボタン、として描画し分ける。
--}}
@props([
    'href' => null,
    'method' => null,
    'variant' => 'default',
    'icon' => null,
])

@php
    $variants = [
        'default' => 'text-ink-900 hover:bg-ink-50',
        'danger' => 'text-danger-700 hover:bg-danger-50',
    ];
    $variantClass = $variants[$variant] ?? $variants['default'];
    $baseClass = "flex w-full items-center gap-2 px-3 py-2 text-sm transition-colors duration-fast $variantClass";
@endphp

@if ($method && $href)
    @php $formId = 'dd-form-' . md5($href . $method); @endphp
    <form novalidate method="POST" action="{{ $href }}" id="{{ $formId }}" class="block">
        @csrf
        @method(strtoupper($method))
    </form>
    <button
        type="submit"
        form="{{ $formId }}"
        role="menuitem"
        {{ $attributes->merge(['class' => $baseClass]) }}
    >
        @if ($icon)<x-icon :name="$icon" class="w-4 h-4" />@endif
        {{ $slot }}
    </button>
@elseif ($href)
    <a
        href="{{ $href }}"
        role="menuitem"
        {{ $attributes->merge(['class' => $baseClass]) }}
    >
        @if ($icon)<x-icon :name="$icon" class="w-4 h-4" />@endif
        {{ $slot }}
    </a>
@else
    <button
        type="button"
        role="menuitem"
        {{ $attributes->merge(['class' => $baseClass]) }}
    >
        @if ($icon)<x-icon :name="$icon" class="w-4 h-4" />@endif
        {{ $slot }}
    </button>
@endif
