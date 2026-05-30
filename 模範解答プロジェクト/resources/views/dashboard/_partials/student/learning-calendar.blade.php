@props([
    'streak',
    'calendar',
])

@php
    /** @var ?\App\Services\Learning\StreakSummary $streak */
    /** @var ?\App\Services\Learning\LearningCalendar $calendar */
    $monthLabel = null;
    if ($calendar !== null) {
        $hours = intdiv($calendar->monthTotalMinutes, 60);
        $minutes = $calendar->monthTotalMinutes % 60;
        $monthLabel = ($hours ? $hours . 'h ' : '') . $minutes . 'm';
    }
@endphp

<x-card class="lc-card" data-scheme="teal" padding="md">
    <div class="lc-top">
        <div class="lc-title">
            <x-icon name="calendar-days" />
            学習カレンダー
        </div>
    </div>

    {{-- ストリーク stat strip: 連続日数 (gradient hero) + 最長 + 今月の学習時間 --}}
    @if ($streak !== null)
        <div class="mb-4 rounded-2xl bg-gradient-to-br from-warning-200 via-success-200 to-primary-200 px-4 py-3.5 text-ink-900 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white/45 text-warning-700">
                    <x-icon name="bolt" class="h-4 w-4" />
                </span>
                <div>
                    <div class="font-display text-2xl font-extrabold leading-none tracking-tight tabular-nums">
                        {{ $streak->currentStreak }}<span class="ml-0.5 text-sm font-bold opacity-60">日</span>
                    </div>
                    <div class="mt-0.5 text-[11px] font-semibold opacity-70">連続学習中</div>
                </div>
                <div class="ml-auto flex flex-col gap-1">
                    <div class="flex items-center justify-between gap-4 text-[11px]">
                        <span class="opacity-65">最長</span>
                        <span class="font-display font-bold tabular-nums">{{ $streak->longestStreak }} 日</span>
                    </div>
                    @if ($monthLabel !== null)
                        <div class="flex items-center justify-between gap-4 text-[11px]">
                            <span class="opacity-65">今月</span>
                            <span class="font-display font-bold tabular-nums">{{ $monthLabel }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- 草グリッド本体。resources/js/dashboard/learning-calendar.js が data 属性を読んで描画する --}}
    @if ($calendar !== null)
        <div
            id="lc-grass"
            data-today="{{ $calendar->today }}"
            data-months="4"
            data-days="{{ json_encode($calendar->daysMap, JSON_FORCE_OBJECT) }}"
        ></div>
    @elseif ($streak === null)
        @include('dashboard._partials.empty-state', ['message' => '学習カレンダーを取得できませんでした。'])
    @endif
</x-card>
