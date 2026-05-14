@props([
    'legend' => null,
])

<fieldset {{ $attributes->merge(['class' => 'space-y-4']) }}>
    @if ($legend)
        <legend class="text-sm font-semibold text-ink-900">{{ $legend }}</legend>
    @endif
    {{ $slot }}
</fieldset>
