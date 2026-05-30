{{--
    テーブルセル(<td>)。<x-table.row> の中に並べる本文セル。
    スロットにセルの中身を入れる。class で寄せ方向等を上書きできる。
--}}
<td {{ $attributes->merge(['class' => 'px-4 py-3 text-sm text-ink-900']) }}>
    {{ $slot }}
</td>
