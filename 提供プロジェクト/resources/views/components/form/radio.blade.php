{{--
    ラジオボタン。ラベル付きの単一選択肢。同じ name で複数並べて 1 つを選ばせる。
    props: name(必須)・value(この選択肢の値、必須)・label・checked(初期選択)・disabled。id は未指定なら name-value から自動生成。
--}}
@props([
    'name',
    'label' => null,
    'value',
    'checked' => false,
    'disabled' => false,
])

@php
    $id = $attributes->get('id') ?? ($name . '-' . $value);
@endphp

<label for="{{ $id }}" class="inline-flex items-center gap-2 text-sm text-ink-900 cursor-pointer @if ($disabled) opacity-50 cursor-not-allowed @endif">
    <input
        type="radio"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ $value }}"
        @if ($checked) checked @endif
        @if ($disabled) disabled @endif
        {{ $attributes->except(['id'])->merge(['class' => 'h-[18px] w-[18px] border-ink-300 text-primary-600 focus:ring-primary-500 focus:ring-offset-0']) }}
    >
    @if ($label){{ $label }}@endif
    {{ $slot }}
</label>
