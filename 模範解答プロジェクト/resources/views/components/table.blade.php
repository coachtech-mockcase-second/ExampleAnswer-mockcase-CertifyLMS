@props([
    'head' => null,
])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-[var(--border-subtle)] bg-surface-raised']) }}>
    <table class="w-full">
        @isset($head)
            <thead class="bg-surface-sunken/60 border-b border-[var(--border-subtle)]">
                {{ $head }}
            </thead>
        @endisset
        <tbody class="divide-y divide-[var(--border-subtle)]">
            {{ $slot }}
        </tbody>
    </table>
</div>
