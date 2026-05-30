{{--
    資格詳細画面の基本情報カード partial。
    構成: メタ情報グリッド(カテゴリ / 難易度 / 受講登録数 / 公開日時 / アーカイブ日時 / 最終更新) → 説明文(あれば)
--}}
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
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">受講登録数</div>
            <div class="mt-1 text-sm font-semibold text-ink-900 tabular-nums">{{ $certification->enrollments_count ?? 0 }} 件</div>
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
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">最終更新</div>
            <div class="mt-1 text-sm font-mono text-ink-700 tabular-nums">{{ $certification->updated_at?->format('Y-m-d H:i') ?? '—' }}</div>
        </div>
    </div>

    @if ($certification->description)
        <div class="mt-6 border-t border-subtle pt-4">
            <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">説明</div>
            <p class="mt-2 text-sm text-ink-700 whitespace-pre-line leading-relaxed">{{ $certification->description }}</p>
        </div>
    @endif
</x-card>
