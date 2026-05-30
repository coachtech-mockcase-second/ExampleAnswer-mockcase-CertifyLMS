{{--
    テーブルコンテナ。枠線つきのテーブルを描画する外枠。
    props: head スロット(<thead> 内の見出し行) + 本文スロット(<tbody> 内の行)。
    中身は <x-table.row> / <x-table.heading> / <x-table.cell> を組み合わせて並べる。
--}}
@props([
    'head' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-subtle bg-surface-raised']) }}>
    <table class="w-full">
        @isset($head)
            <thead class="bg-surface-sunken/60 border-b border-subtle">
                {{ $head }}
            </thead>
        @endisset
        <tbody class="divide-y divide-subtle">
            {{ $slot }}
        </tbody>
    </table>
</div>
