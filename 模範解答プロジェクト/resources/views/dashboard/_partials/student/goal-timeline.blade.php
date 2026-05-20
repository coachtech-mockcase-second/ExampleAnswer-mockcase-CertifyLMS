@props([
    'goals',
])

<x-card padding="md">
    <div class="flex items-baseline gap-2 mb-3">
        <h2 class="text-base font-bold text-ink-900 flex items-center gap-2">
            <x-icon name="check-badge" class="w-4 h-4 text-secondary-600" />
            個人目標
        </h2>
        <span class="flex-1"></span>
        @if ($goals !== null && $goals->isNotEmpty())
            <a href="{{ route('enrollments.index') }}" class="text-xs text-primary-700 hover:underline">受講中資格から追加 +</a>
        @endif
    </div>

    @if ($goals === null)
        @include('dashboard._partials.empty-state', ['message' => '個人目標を取得できませんでした。'])
    @elseif ($goals->isEmpty())
        <p class="text-sm text-ink-500 py-2">
            個人目標はまだありません。受講中資格から目標を追加すると進捗が見える化されます。
        </p>
    @else
        <ol class="relative pl-6">
            <span class="absolute left-2 top-1.5 bottom-1.5 w-0.5 bg-secondary-100"></span>
            @foreach ($goals as $goal)
                @php
                    $achieved = $goal->isAchieved();
                @endphp
                <li class="relative pb-3 last:pb-0">
                    <span class="absolute -left-5 top-1 w-3 h-3 rounded-full border-2 {{ $achieved ? 'bg-secondary-500 border-secondary-500' : 'bg-white border-secondary-400' }}"></span>
                    @if ($goal->target_date !== null)
                        <div class="text-[11px] text-ink-500 font-mono">
                            {{ $goal->target_date->format('Y/m/d') }} まで
                        </div>
                    @endif
                    <div class="text-sm font-semibold text-ink-900 mt-0.5">{{ $goal->title }}</div>
                    <div class="text-[11px] mt-0.5 {{ $achieved ? 'text-success-700' : 'text-ink-500' }}">
                        {{ $achieved ? '✓ 達成 (' . $goal->achieved_at->format('Y/m/d') . ')' : '進行中' }}
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-card>
