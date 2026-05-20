@props([
    'streak',
])

@php
    /** @var ?\App\Services\Learning\StreakSummary $streak */
@endphp

@if ($streak === null)
    @include('dashboard._partials.empty-state', ['message' => '学習ストリークを取得できませんでした。'])
@else
    <section class="rounded-2xl px-5 py-5 bg-gradient-to-br from-warning-200 via-success-200 to-primary-200 text-ink-900 shadow-md">
        <div class="flex items-center gap-3.5">
            <span class="inline-flex w-10 h-10 items-center justify-center bg-white/45 rounded-xl text-warning-700">
                <x-icon name="bolt" class="w-5 h-5" />
            </span>
            <div>
                <div class="font-display text-3xl font-extrabold leading-none tabular-nums tracking-tight">{{ $streak->currentStreak }}</div>
                <div class="text-xs font-semibold opacity-70 mt-1">日連続で学習中</div>
            </div>
            <div class="ml-auto text-right">
                <div class="text-[10px] uppercase tracking-wider opacity-65">最長</div>
                <div class="font-display text-lg font-bold tabular-nums">{{ $streak->longestStreak }} 日</div>
            </div>
        </div>

        @if ($streak->lastActiveDate !== null)
            <div class="mt-3 text-[11px] text-ink-900/70">
                最終学習: {{ $streak->lastActiveDate->format('Y/m/d') }}
            </div>
        @endif
    </section>
@endif
