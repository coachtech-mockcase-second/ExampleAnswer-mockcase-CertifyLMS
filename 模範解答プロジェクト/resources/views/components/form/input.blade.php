@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'error' => null,
    'placeholder' => null,
    'hint' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
])

@php
    $error = $error ?? $errors->first($name);
    $id = $attributes->get('id') ?? $name;
    $describedBy = collect([
        $hint ? "{$name}-hint" : null,
        $error ? "{$name}-error" : null,
    ])->filter()->implode(' ');

    $inputClasses = 'block w-full rounded-md border bg-white px-3 py-2.5 text-sm text-ink-900 placeholder-ink-400 transition-colors duration-fast focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-0 ';
    $inputClasses .= $error
        ? 'border-danger-500 focus:border-danger-500 focus-visible:ring-danger-500/30'
        : 'border-ink-200 focus:border-primary-500 focus-visible:ring-primary-500/30';
    $inputClasses .= $disabled ? ' bg-surface-sunken text-ink-500 cursor-not-allowed' : '';
@endphp

<div class="space-y-1.5">
    @if ($label)
        <x-form.label :for="$id" :required="$required">{{ $label }}</x-form.label>
    @endif

    <input
        type="{{ $type }}"
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($value !== null) value="{{ $value }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        @if ($readonly) readonly @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['id'])->merge(['class' => $inputClasses]) }}
    >

    @if ($hint)
        <x-form.hint :name="$name">{{ $hint }}</x-form.hint>
    @endif
    <x-form.error :name="$name" :message="$error" />
</div>
