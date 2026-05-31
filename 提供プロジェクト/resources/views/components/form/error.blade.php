{{--
    フィールドエラー文。入力欄の下に出すエラーメッセージ 1 行。
    props: name(このフィールドのエラーを拾う名前)・message(直接渡す場合)。表示すべきエラーが無ければ何も描画しない。
--}}
@props([
    'name' => null,
    'message' => null,
])

@php
    $text = $message ?? ($name ? $errors->first($name) : null);
@endphp

@if ($text)
    <p
        @if ($name) id="{{ $name }}-error" @endif
        role="alert"
        {{ $attributes->merge(['class' => 'text-xs text-danger-700']) }}
    >
        {{ $text }}
    </p>
@endif
