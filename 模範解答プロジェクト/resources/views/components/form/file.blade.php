@props([
    'name',
    'label' => null,
    'accept' => null,
    'error' => null,
    'hint' => null,
    'required' => false,
    'disabled' => false,
    'multiple' => false,
])

@php
    $error = $error ?? $errors->first($name);
    $id = $attributes->get('id') ?? $name;
    $describedBy = collect([
        $hint ? "{$name}-hint" : null,
        $error ? "{$name}-error" : null,
    ])->filter()->implode(' ');

    $inputClasses = 'block w-full text-sm text-ink-700 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-primary-50 file:text-primary-700 file:font-semibold hover:file:bg-primary-100 focus:outline-none';
@endphp

<div class="space-y-1.5">
    @if ($label)
        <x-form.label :for="$id" :required="$required">{{ $label }}</x-form.label>
    @endif

    <input
        type="file"
        id="{{ $id }}"
        name="{{ $name }}{{ $multiple ? '[]' : '' }}"
        @if ($accept) accept="{{ $accept }}" @endif
        @if ($required) required @endif
        @if ($disabled) disabled @endif
        @if ($multiple) multiple @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['id'])->merge(['class' => $inputClasses]) }}
    >

    @if ($hint)
        <x-form.hint :name="$name">{{ $hint }}</x-form.hint>
    @endif
    <x-form.error :name="$name" :message="$error" />
</div>
