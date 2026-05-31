{{--
    フィールド補足文。入力欄の下に出す補足・ヒント 1 行。
    props: name(対応フィールド名。aria の紐付け id を振る用、任意) + 補足テキストスロット。
--}}
@props([
    'name' => null,
])

<p
    @if ($name) id="{{ $name }}-hint" @endif
    {{ $attributes->merge(['class' => 'text-xs text-ink-500']) }}
>
    {{ $slot }}
</p>
