@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'error' => null,
    'placeholder' => null,
    'hint' => null,
    'required' => false,
    'disabled' => false,
])

@php
    $error = $error ?? $errors->first($name);
    $id = $attributes->get('id') ?? $name;
    $describedBy = collect([
        $hint ? "{$name}-hint" : null,
        $error ? "{$name}-error" : null,
    ])->filter()->implode(' ');

    $inputClasses = 'block w-full rounded-md border bg-white pl-3 pr-8 py-2.5 text-sm text-ink-900 transition-colors duration-fast focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-0 ';
    $inputClasses .= $error
        ? 'border-danger-500 focus:border-danger-500 focus-visible:ring-danger-500/30'
        : 'border-ink-200 focus:border-primary-500 focus-visible:ring-primary-500/30';
@endphp

<div class="space-y-1.5">
    @if ($label)
        <x-form.label :for="$id" :required="$required">{{ $label }}</x-form.label>
    @endif

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['id'])->merge(['class' => $inputClasses]) }}
    >
        @if ($placeholder)
            <option value="" disabled @selected($value === null)>{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>

    @if ($hint)
        <x-form.hint :name="$name">{{ $hint }}</x-form.hint>
    @endif
    <x-form.error :name="$name" :message="$error" />
</div>
