@props([
    'name' => null,
])

<p
    @if ($name) id="{{ $name }}-hint" @endif
    {{ $attributes->merge(['class' => 'text-xs text-ink-500']) }}
>
    {{ $slot }}
</p>
