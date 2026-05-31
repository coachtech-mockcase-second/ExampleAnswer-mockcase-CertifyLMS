{{--
    テーブル行(<tr>)。見出し行・本文行の両方に使う行コンテナ(ホバーで背景が変わる)。
    スロットに <x-table.heading> / <x-table.cell> を並べる。
--}}
<tr {{ $attributes->merge(['class' => 'hover:bg-surface-sunken/40 transition-colors duration-fast']) }}>
    {{ $slot }}
</tr>
