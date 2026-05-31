{{--
    フィールドセット。関連する入力欄を見出し付きでひとまとめにする枠。
    props: legend(グループ見出し、任意) + 本文スロット(内側に入力欄を縦積みで並べる)。
--}}
@props([
    'legend' => null,
])

<fieldset {{ $attributes->merge(['class' => 'space-y-4']) }}>
    @if ($legend)
        <legend class="text-sm font-semibold text-ink-900">{{ $legend }}</legend>
    @endif
    {{ $slot }}
</fieldset>
