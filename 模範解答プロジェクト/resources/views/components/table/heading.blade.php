{{--
    テーブル見出しセル(<th>)。<thead> の見出し行に並べる列見出し。
    スロットに見出し文言を入れる。class で寄せ方向等を上書きできる。
--}}
<th
    scope="col"
    {{ $attributes->merge(['class' => 'text-left text-[10px] font-semibold uppercase tracking-wider text-ink-500 px-4 py-3']) }}
>
    {{ $slot }}
</th>
