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
