@props([
    'question',
    'href',
    'index' => null,
])

@php
    $attempt = $question->sectionQuestionAttempts->first();
    $bodyExcerpt = mb_strimwidth(strip_tags((string) $question->body), 0, 80, '…');
    $categoryName = $question->category?->name;
@endphp

<a href="{{ $href }}"
    class="block rounded-2xl border border-[var(--border-subtle)] bg-white p-5 transition-all hover:-translate-y-px hover:border-primary-300 hover:shadow-md">
    <div class="flex items-start gap-4">
        @if ($index !== null)
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-bold text-primary-700">
                {{ $index }}
            </span>
        @endif

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                @if ($categoryName)
                    <x-badge variant="info" size="sm">{{ $categoryName }}</x-badge>
                @endif

                @if ($attempt)
                    <x-badge :variant="$attempt->last_is_correct ? 'success' : 'danger'" size="sm">
                        最新: {{ $attempt->last_is_correct ? '正解' : '誤答' }}
                    </x-badge>
                    <span class="text-[11px] text-ink-500">{{ $attempt->attempt_count }} 回挑戦</span>
                @else
                    <x-badge variant="gray" size="sm">未挑戦</x-badge>
                @endif
            </div>

            <p class="mt-2 text-sm leading-relaxed text-ink-800">
                {{ $bodyExcerpt }}
            </p>
        </div>

        <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-ink-50 text-ink-500">
            <x-icon name="chevron-right" class="w-4 h-4" />
        </span>
    </div>
</a>
