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
