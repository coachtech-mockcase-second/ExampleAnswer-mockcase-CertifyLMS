@props([
    'icon' => 'inbox',
    'title' => '',
    'description' => null,
    'action' => null,
])

<div {{ $attributes->merge(['class' => 'text-center py-12 px-6']) }}>
    <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-500 mb-4">
        <x-icon :name="$icon" class="w-6 h-6" />
    </div>

    @if ($title)
        <h3 class="text-base font-semibold text-ink-900">{{ $title }}</h3>
    @endif

    @if ($description)
        <p class="mt-1 text-sm text-ink-500">{{ $description }}</p>
    @endif

    @if ($action)
        <div class="mt-6">
            {{ $action }}
        </div>
    @endif
</div>
