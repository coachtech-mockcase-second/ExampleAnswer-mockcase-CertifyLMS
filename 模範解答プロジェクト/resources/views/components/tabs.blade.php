@props([
    'tabs' => [],
    'active' => null,
    'param' => 'tab',
])

@php
    $active = $active ?? request()->query($param, array_key_first($tabs));
@endphp

<div class="border-b border-[var(--border-subtle)]" {{ $attributes }}>
    <nav class="-mb-px flex gap-6" aria-label="タブ">
        @foreach ($tabs as $key => $label)
            @php
                $isActive = (string) $active === (string) $key;
                $url = request()->fullUrlWithQuery([$param => $key]);
            @endphp
            <a
                href="{{ $url }}"
                role="tab"
                @if ($isActive) aria-selected="true" aria-current="page" @endif
                class="border-b-2 px-1 py-3 text-sm font-medium transition-colors duration-fast {{ $isActive ? 'border-primary-600 text-primary-700' : 'border-transparent text-ink-500 hover:text-ink-900 hover:border-ink-300' }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
