@props([
    'enrollments',
])

<x-card padding="none">
    <div class="px-5 py-4 flex items-center gap-3 border-b border-[var(--border-subtle)]">
        <h2 class="text-base font-bold text-ink-900">担当資格に登録した受講生</h2>
        <span class="text-xs text-ink-500">{{ $enrollments->count() }} 名</span>
        <span class="flex-1"></span>
    </div>

    @if ($enrollments->isEmpty())
        <div class="px-5 py-6 text-sm text-ink-500">
            担当資格にまだ受講生がいません。資格マスタの担当割当を確認するか、招待を発行してください。
        </div>
    @else
        <ul>
            @foreach ($enrollments as $enrollment)
                @php
                    /** @var \Carbon\CarbonInterface|null $lastActivityAt */
                    $lastActivityAt = $enrollment->last_activity_at;
                    if ($lastActivityAt !== null && ! $lastActivityAt instanceof \Carbon\CarbonInterface) {
                        $lastActivityAt = \Carbon\Carbon::parse((string) $lastActivityAt);
                    }
                @endphp
                <li class="grid items-center gap-3.5 px-5 py-3 border-b border-[var(--border-subtle)] last:border-b-0 hover:bg-surface-canvas/60"
                    style="grid-template-columns: auto 1fr auto auto;">
                    <x-avatar :name="$enrollment->user->name" size="md" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink-900 truncate">{{ $enrollment->user->name }}</p>
                        <p class="text-[11px] text-ink-500 mt-0.5 truncate">
                            {{ $enrollment->certification->name }} · {{ $enrollment->current_term->label() }}
                        </p>
                    </div>
                    <x-badge variant="{{ $enrollment->status->value === 'passed' ? 'success' : 'primary' }}" size="sm">
                        {{ $enrollment->status->label() }}
                    </x-badge>
                    <div class="text-[11px] text-ink-600 font-mono text-right min-w-[88px]">
                        最終活動<br>
                        <b class="text-ink-800 font-semibold">
                            {{ $lastActivityAt !== null ? $lastActivityAt->diffForHumans() : '記録なし' }}
                        </b>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
