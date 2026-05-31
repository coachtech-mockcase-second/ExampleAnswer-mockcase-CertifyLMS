{{--
    受講中の資格カード（1 資格 = 1 枚、学習中のみ）。受講生ダッシュボードの主役。
    構成: ヘッダ（資格名 + ターム/合格可能性バッジ + 受験日カウントダウン）→ 進捗バー
          → 残学習時間 / 日次推奨 / 弱点カテゴリ → 操作（教材へ）→ 修了条件達成時の修了証の受領。
--}}
@props([
    'card',
])

@php
    /** @var \App\UseCases\Dashboard\ViewModels\StudentEnrollmentCard $card */
    $progressPercent = $card->progressRatio !== null ? (int) round($card->progressRatio * 100) : null;
    $bandColor = $card->passProbabilityBand?->color() ?? 'gray';
    $bandLabel = $card->passProbabilityBand?->label();
    $termColor = $card->currentTerm->value === 'mock_practice' ? 'secondary' : 'info';

    $countdownLabel = '試験日まで';
    $countdownColor = 'text-primary-700';
    $countdownUnit = '日';
    if ($card->daysUntilExam !== null) {
        if ($card->daysUntilExam < 0) {
            $countdownLabel = '受験日から';
            $countdownColor = 'text-danger-700';
            $countdownUnit = '日経過';
        } elseif ($card->daysUntilExam === 0) {
            $countdownLabel = '受験日';
            $countdownColor = 'text-warning-700';
            $countdownUnit = '';
        }
    }
@endphp

<article class="bg-surface-raised border border-subtle rounded-2xl px-4 py-3.5 shadow-sm flex flex-col gap-3">
    <header class="flex items-start gap-3">
        <span class="inline-flex w-9 h-9 flex-shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
            <x-icon name="academic-cap" class="w-4 h-4" />
        </span>
        <div class="flex-1 min-w-0">
            <a href="{{ route('enrollments.show', $card->enrollmentId) }}"
               class="block font-display text-base font-bold text-ink-900 tracking-tight truncate hover:text-primary-700 transition-colors">
                {{ $card->certificationName }}
            </a>
            <div class="flex gap-1.5 items-center mt-1 flex-wrap">
                <x-badge variant="{{ $termColor }}" size="sm">{{ $card->currentTerm->label() }}</x-badge>
                @if ($bandLabel !== null)
                    <x-badge variant="{{ $bandColor }}" size="sm">{{ $bandLabel }}</x-badge>
                @endif
            </div>
        </div>
        <div class="text-right flex-shrink-0">
            @if ($card->daysUntilExam !== null)
                <div class="text-[10px] uppercase tracking-wider text-ink-500">{{ $countdownLabel }}</div>
                <div class="font-display text-2xl font-extrabold {{ $countdownColor }} leading-none tabular-nums tracking-tight mt-0.5">
                    @if ($card->daysUntilExam === 0)
                        本日
                    @else
                        {{ abs($card->daysUntilExam) }}<span class="text-sm font-bold ml-0.5">{{ $countdownUnit }}</span>
                    @endif
                </div>
                <div class="text-[11px] text-ink-600 font-mono mt-0.5">{{ $card->examDate->format('Y/m/d') }} 実施</div>
            @else
                <a href="{{ route('enrollments.show', $card->enrollmentId) }}"
                   class="inline-flex items-center gap-1 text-xs text-primary-700 hover:underline">
                    <x-icon name="calendar" class="w-3 h-3" />
                    受験日を設定
                </a>
            @endif
        </div>
    </header>

    @if ($card->progressRatio !== null)
        <div class="flex flex-col gap-1.5">
            <div class="flex justify-between items-baseline text-xs text-ink-600">
                <span>進捗</span>
                <span class="font-display text-base font-bold tabular-nums tracking-tight text-ink-900">{{ $progressPercent }}%</span>
            </div>
            <div class="h-2 bg-ink-100 rounded-full overflow-hidden">
                <div class="h-full bg-primary-500 rounded-full" style="width: {{ $progressPercent }}%"></div>
            </div>
        </div>
    @else
        @include('dashboard._partials.empty-state', ['message' => '進捗を取得できませんでした。'])
    @endif

    <div class="flex flex-wrap gap-3 pt-2.5 border-t border-subtle text-xs">
        @if ($card->learningHourTarget !== null)
            <div class="flex flex-col">
                <span class="text-[10px] uppercase tracking-wider font-semibold text-ink-500">残学習時間</span>
                <span class="font-display text-sm font-bold text-ink-900 tabular-nums">
                    @if ($card->learningHourTarget->remainingHours !== null)
                        {{ number_format($card->learningHourTarget->remainingHours, 1) }} h
                    @else
                        未設定
                    @endif
                </span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] uppercase tracking-wider font-semibold text-ink-500">日次推奨</span>
                <span class="font-display text-sm font-bold text-ink-900 tabular-nums">
                    @if ($card->learningHourTarget->dailyRecommendedHours !== null)
                        {{ number_format($card->learningHourTarget->dailyRecommendedHours, 1) }} h
                    @else
                        未設定
                    @endif
                </span>
            </div>
        @endif

        @if ($card->weakCategories->isNotEmpty())
            <div class="flex flex-col flex-1 min-w-[150px]">
                <span class="text-[10px] uppercase tracking-wider font-semibold text-ink-500">弱点カテゴリ</span>
                <div class="flex gap-1 flex-wrap mt-1">
                    @foreach ($card->weakCategories as $category)
                        <a href="{{ route('quiz.drills.category', ['enrollment' => $card->enrollmentId, 'questionCategory' => $category->id]) }}"
                           class="text-[10px] px-2 py-0.5 rounded-full bg-danger-50 text-danger-800 hover:bg-danger-100 font-semibold transition-colors">
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="flex items-center justify-between gap-2">
        <p class="text-[11px] text-ink-500 flex items-center gap-1.5">
            <x-icon name="information-circle" class="w-3 h-3 flex-shrink-0" />
            修了条件: 公開模試すべての合格点超え
        </p>
        <x-link-button :href="route('learning.enrollments.show', $card->enrollmentId)" variant="primary" size="sm">
            <x-icon name="book-open" class="w-4 h-4" />
            教材へ
        </x-link-button>
    </div>

    @if ($card->canReceiveCertificate)
        <form novalidate method="POST" action="{{ route('enrollments.receiveCertificate', $card->enrollmentId) }}">
            @csrf
            <div class="flex items-center gap-2.5 px-3.5 py-2.5 bg-success-50 border border-success-200 rounded-[10px]">
                <x-icon name="check-badge" class="w-4 h-4 text-success-700 flex-shrink-0" />
                <span class="text-xs text-success-900 flex-1 leading-relaxed">
                    <b class="font-bold">修了条件を達成しました!</b> 修了証を受け取ると以降は復習モードでの学習となります。
                </span>
                <button type="submit"
                        class="bg-success-600 hover:bg-success-700 text-white px-3.5 py-2 text-xs font-bold rounded-lg inline-flex items-center gap-1 transition-colors">
                    <x-icon name="check-circle" class="w-3 h-3" />
                    修了証を受け取る
                </button>
            </div>
        </form>
    @endif
</article>
