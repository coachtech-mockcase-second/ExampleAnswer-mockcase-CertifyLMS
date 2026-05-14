<a href="{{ route('certifications.show', $certification) }}" class="group">
    <x-card padding="lg" shadow="sm" class="h-full transition-shadow group-hover:shadow-md group-hover:border-primary-200">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="text-base font-semibold text-ink-900 group-hover:text-primary-700 transition-colors truncate">{{ $certification->name }}</div>
                <div class="text-xs text-ink-500 font-mono mt-1">{{ $certification->code }}</div>
            </div>
            @if ($isEnrolled)
                <x-badge variant="success" size="sm">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-current"></span>
                    受講中
                </x-badge>
            @endif
        </div>

        <div class="mt-3 flex items-center gap-2 flex-wrap">
            <x-badge variant="info" size="sm">{{ $certification->category?->name ?? '—' }}</x-badge>
            <x-badge variant="gray" size="sm">{{ $certification->difficulty->label() }}</x-badge>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-3 border-t border-[var(--border-subtle)] pt-4 text-center">
            <div>
                <div class="text-[10px] uppercase tracking-wider text-ink-500 font-semibold">合格点</div>
                <div class="mt-1 text-sm font-semibold text-ink-900 tabular-nums">{{ $certification->passing_score }}%</div>
            </div>
            <div>
                <div class="text-[10px] uppercase tracking-wider text-ink-500 font-semibold">問題数</div>
                <div class="mt-1 text-sm font-semibold text-ink-900 tabular-nums">{{ $certification->total_questions }}</div>
            </div>
            <div>
                <div class="text-[10px] uppercase tracking-wider text-ink-500 font-semibold">時間</div>
                <div class="mt-1 text-sm font-semibold text-ink-900 tabular-nums">{{ $certification->exam_duration_minutes }}分</div>
            </div>
        </div>
    </x-card>
</a>
