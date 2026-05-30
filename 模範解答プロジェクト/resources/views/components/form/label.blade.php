{{--
    フォームラベル。入力欄に紐づく見出し。required=true で必須マーク(*)を付ける。
    props: for(紐付ける入力欄の id)・required(必須マーク表示) + ラベル文言スロット。
--}}
@props([
    'for' => null,
    'required' => false,
])

<label
    @if ($for) for="{{ $for }}" @endif
    {{ $attributes->merge(['class' => 'block text-sm font-semibold text-ink-900']) }}
>
    {{ $slot }}
    @if ($required)
        <span class="text-danger-600 ml-0.5" aria-hidden="true">*</span>
        <span class="sr-only">必須</span>
    @endif
</label>
