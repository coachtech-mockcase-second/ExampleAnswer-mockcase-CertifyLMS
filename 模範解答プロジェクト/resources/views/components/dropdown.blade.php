{{--
    ドロップダウンメニュー。trigger を押すと下にメニューを開閉するコンテナ。
    props: align(left/right メニューの寄せ方向) + trigger スロット(開閉ボタン) + 本文スロット(<x-dropdown.item> を並べる)。
    開閉は素の JS。外側クリック / Esc で閉じる。
--}}
@props([
    'align' => 'right',
])

@php
    $alignment = $align === 'left' ? 'left-0' : 'right-0';
@endphp

<div data-dropdown {{ $attributes->merge(['class' => 'relative inline-block']) }}>
    @isset($trigger)
        <div data-dropdown-trigger aria-haspopup="menu" aria-expanded="false">
            {{ $trigger }}
        </div>
    @endisset

    <div
        data-dropdown-menu
        role="menu"
        class="absolute {{ $alignment }} top-full mt-1 z-40 hidden min-w-[12rem] py-1 bg-surface-raised border border-subtle rounded-md shadow-md"
    >
        {{ $slot }}
    </div>
</div>
