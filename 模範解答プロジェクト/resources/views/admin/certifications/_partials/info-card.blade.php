<x-card class="mt-6" padding="lg" shadow="sm">
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <div>
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">カテゴリ</div>
            <div class="mt-1 text-sm font-semibold text-ink-900">{{ $certification->category?->name ?? '—' }}</div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">難易度</div>
            <div class="mt-1 text-sm font-semibold text-ink-900">{{ $certification->difficulty->label() }}</div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">合格点</div>
            <div class="mt-1 text-sm font-semibold text-ink-900 tabular-nums">{{ $certification->passing_score }}%</div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">総問題数</div>
            <div class="mt-1 text-sm font-semibold text-ink-900 tabular-nums">{{ $certification->total_questions }} 問</div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">試験時間</div>
            <div class="mt-1 text-sm font-semibold text-ink-900 tabular-nums">{{ $certification->exam_duration_minutes }} 分</div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">スラッグ</div>
            <div class="mt-1 text-sm font-mono text-ink-700 break-all">{{ $certification->slug }}</div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">公開日時</div>
            <div class="mt-1 text-sm font-mono text-ink-700 tabular-nums">{{ $certification->published_at?->format('Y-m-d H:i') ?? '—' }}</div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">アーカイブ日時</div>
            <div class="mt-1 text-sm font-mono text-ink-700 tabular-nums">{{ $certification->archived_at?->format('Y-m-d H:i') ?? '—' }}</div>
        </div>

        <div>
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">受講登録数</div>
            <div class="mt-1 text-sm font-semibold text-ink-900 tabular-nums">{{ $certification->enrollments_count ?? 0 }} 件</div>
        </div>
    </div>

    @if ($certification->description)
        <div class="mt-6 border-t border-[var(--border-subtle)] pt-4">
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">説明</div>
            <p class="mt-2 text-sm text-ink-700 whitespace-pre-line leading-relaxed">{{ $certification->description }}</p>
        </div>
    @endif
</x-card>
