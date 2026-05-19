@php
    use App\Enums\QaThreadStatus;

    $isResolved = $thread->status === QaThreadStatus::Resolved;
    $replyCount = (int) ($thread->replies_count ?? 0);

    [$badgeVariant, $badgeLabel, $statsClass] = match (true) {
        $isResolved => ['success', '✓ 解決済', 'text-success-700'],
        $replyCount === 0 => ['danger', '未回答', 'text-warning-700'],
        default => ['info', '対応中', 'text-ink-900'],
    };

    $authorInitials = mb_substr($thread->user?->name ?? '?', 0, 2);
    $recentLabel = $isResolved && $thread->resolved_at
        ? '解決 '.$thread->resolved_at->diffForHumans(now(), ['parts' => 1, 'short' => true])
        : ($thread->updated_at ? '最終 '.$thread->updated_at->diffForHumans(now(), ['parts' => 1, 'short' => true]) : '—');
    $preview = mb_strimwidth(preg_replace('/\s+/', ' ', $thread->body ?? ''), 0, 140, '…');
@endphp

<a
    href="{{ route('qa-board.show', $thread) }}"
    class="block bg-surface-raised border rounded-2xl px-4 py-4 transition hover:border-primary-200 hover:shadow-md {{ $isResolved ? 'bg-success-50/40 border-success-200' : 'border-[var(--border-subtle,#E6EDEB)]' }}"
>
    <div class="grid grid-cols-[auto_1fr_auto] items-start gap-4">
        <div class="flex flex-col items-center gap-0.5 min-w-[56px]">
            <div class="font-display text-[22px] font-extrabold leading-none tracking-tight tabular-nums {{ $statsClass }}">
                {{ $replyCount }}
            </div>
            <div class="text-[10px] font-semibold uppercase tracking-wider text-ink-500">回答</div>
        </div>

        <div class="min-w-0">
            <div class="text-[15px] font-bold leading-snug line-clamp-2 {{ $isResolved ? 'text-ink-700' : 'text-ink-900' }}">
                {{ $thread->title }}
            </div>
            @if ($preview !== '')
                <div class="text-xs text-ink-600 mt-1.5 leading-relaxed line-clamp-2">
                    {{ $preview }}
                </div>
            @endif
            <div class="flex flex-wrap items-center gap-2.5 mt-1.5 text-[11px] text-ink-500">
                <x-avatar :src="$thread->user?->avatar_url" :name="$thread->user?->name ?? '?'" size="sm" />
                <span class="font-medium text-ink-700">{{ $thread->user?->name ?? '不明' }}</span>
                <span class="inline-block w-1 h-1 rounded-full bg-ink-300"></span>
                <x-badge variant="gray" size="sm">{{ $thread->certification?->name ?? '資格未設定' }}</x-badge>
                <span class="inline-block w-1 h-1 rounded-full bg-ink-300"></span>
                <span>{{ $thread->created_at?->diffForHumans() }}</span>
            </div>
        </div>

        <div class="flex flex-col items-end gap-1.5 min-w-[88px]">
            <x-badge :variant="$badgeVariant" size="sm">{{ $badgeLabel }}</x-badge>
            <div class="text-[11px] text-ink-500 font-mono">{{ $recentLabel }}</div>
        </div>
    </div>
</a>
