@php
    $hasEnrollments = $user->relationLoaded('enrollments') && $user->enrollments->isNotEmpty();
@endphp

<x-card padding="none" shadow="sm">
    <x-slot:header>
        <div class="flex items-center gap-2">
            <x-icon name="academic-cap" class="w-4 h-4 text-primary-600" />
            <span>受講中資格</span>
            @if ($hasEnrollments)
                <span class="text-xs font-normal text-ink-500">{{ $user->enrollments->count() }} 件</span>
            @endif
        </div>
    </x-slot:header>

    @if ($hasEnrollments)
        <ul class="divide-y divide-[var(--border-subtle)]">
            @foreach ($user->enrollments->take(10) as $enrollment)
                <li class="px-6 py-3 flex items-center justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-ink-900 truncate">
                            {{ $enrollment->certification?->name ?? '—' }}
                        </div>
                        <div class="mt-1 flex items-center gap-2 text-xs text-ink-500">
                            <span>{{ optional($enrollment->status)->label() ?? $enrollment->status }}</span>
                            @if ($enrollment->exam_date)
                                <span aria-hidden="true">·</span>
                                <span class="font-mono tabular-nums">受験日 {{ $enrollment->exam_date }}</span>
                            @endif
                        </div>
                    </div>
                    @if (Route::has('enrollments.show'))
                        <x-link-button
                            href="{{ route('enrollments.show', $enrollment) }}"
                            variant="ghost"
                            size="sm"
                        >
                            詳細
                            <x-icon name="chevron-right" class="w-4 h-4" />
                        </x-link-button>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <x-empty-state
            icon="academic-cap"
            title="受講中の資格はありません"
            description="この受講生はまだ資格を受講していません。"
        />
    @endif
</x-card>
