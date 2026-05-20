@props([
    'threads',
    'totalCount',
])

<x-card padding="md">
    <div class="flex items-baseline gap-2 mb-3">
        <h2 class="text-base font-bold text-ink-900 flex items-center gap-2">
            <x-icon name="question-mark-circle" class="w-4 h-4 text-warning-600" />
            未回答 質問
        </h2>
        <span class="flex-1"></span>
        <a href="{{ route('qa-board.index') }}" class="text-xs text-primary-700 hover:underline">
            すべて ({{ $totalCount ?? 0 }}) &rarr;
        </a>
    </div>

    @if ($threads === null || $totalCount === null)
        @include('dashboard._partials.empty-state', ['message' => '未回答 Q&A を取得できませんでした。'])
    @elseif ($threads->isEmpty())
        <p class="text-sm text-ink-500 py-2">担当資格の未回答 Q&A はありません。</p>
    @else
        <ul class="flex flex-col gap-2.5">
            @foreach ($threads as $thread)
                <li class="px-3 py-2.5 bg-surface-canvas rounded-[10px] border border-[var(--border-subtle)]">
                    <a href="{{ route('qa-board.show', $thread) }}" class="block">
                        <p class="text-sm font-semibold text-ink-900 line-clamp-2">{{ $thread->title }}</p>
                        <div class="text-[11px] text-ink-500 mt-1.5 flex gap-2 items-center">
                            <x-badge variant="gray" size="sm">{{ $thread->certification->name }}</x-badge>
                            <span>{{ $thread->created_at->diffForHumans() }} · {{ $thread->user->name }}</span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
