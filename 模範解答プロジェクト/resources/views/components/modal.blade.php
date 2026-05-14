@props([
    'id',
    'title' => null,
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];
    $maxWidth = $sizes[$size] ?? $sizes['md'];
@endphp

@isset($trigger)
    {{ $trigger }}
@endisset

<div
    id="{{ $id }}"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-labelledby="{{ $id }}-title" @endif
    aria-hidden="true"
    inert
    data-modal
    class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-ink-900/60 backdrop-blur-sm transition-opacity duration-normal"
>
    <div class="w-full {{ $maxWidth }} bg-surface-raised rounded-3xl shadow-lg overflow-hidden">
        @if ($title)
            <header class="border-b border-[var(--border-subtle)] px-6 py-4 flex items-center justify-between">
                <h2 id="{{ $id }}-title" class="text-base font-semibold text-ink-900">{{ $title }}</h2>
                <button type="button" data-modal-close="{{ $id }}" aria-label="閉じる" class="text-ink-500 hover:text-ink-900 transition-colors">
                    <x-icon name="x-mark" class="w-5 h-5" />
                </button>
            </header>
        @endif

        @isset($body)
            <div class="px-6 py-5">{{ $body }}</div>
        @else
            <div class="px-6 py-5">{{ $slot }}</div>
        @endisset

        @isset($footer)
            <footer class="border-t border-[var(--border-subtle)] px-6 py-4 flex items-center justify-end gap-2 bg-surface-sunken/50">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
