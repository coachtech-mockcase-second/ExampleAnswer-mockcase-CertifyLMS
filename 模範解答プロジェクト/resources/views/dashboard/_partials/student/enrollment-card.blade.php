@props([
    'card',
])

@php
    /** @var \App\UseCases\Dashboard\ViewModels\StudentEnrollmentCard $card */
    $progressPercent = $card->progressRatio !== null ? (int) round($card->progressRatio * 100) : null;
    $bandColor = $card->passProbabilityBand?->color() ?? 'gray';
    $bandLabel = $card->passProbabilityBand?->label();
    $termColor = $card->currentTerm->value === 'mock_practice' ? 'secondary' : 'info';

    $countdownBadge = null;
    if ($card->daysUntilExam !== null) {
        if ($card->daysUntilExam < 0) {
            $countdownBadge = ['label' => '受験日経過', 'color' => 'text-danger-700'];
        } elseif ($card->daysUntilExam === 0) {
            $countdownBadge = ['label' => '本日', 'color' => 'text-warning-700'];
        }
    }
@endphp

<article class="bg-surface-raised border border-[var(--border-subtle)] rounded-2xl px-5 py-5 shadow-sm flex flex-col gap-3.5">
    <header class="flex items-start gap-3.5">
        <span class="inline-flex w-10 h-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
            <x-icon name="academic-cap" class="w-5 h-5" />
        </span>
        <div class="flex-1 min-w-0">
            <h3 class="font-display text-lg font-bold text-ink-900 tracking-tight truncate">{{ $card->certificationName }}</h3>
            <div class="flex gap-1.5 items-center mt-1 flex-wrap">
                <x-badge variant="{{ $termColor }}" size="sm">{{ $card->currentTerm->label() }}</x-badge>
                @if ($card->isPassed)
                    <x-badge variant="success" size="sm">{{ $card->status->label() }}</x-badge>
                @elseif ($bandLabel !== null)
                    <x-badge variant="{{ $bandColor }}" size="sm">{{ $bandLabel }}</x-badge>
                @endif
            </div>
        </div>
        <div class="text-right flex-shrink-0">
            @if ($card->daysUntilExam !== null)
                <div class="font-display text-3xl font-extrabold text-primary-700 leading-none tabular-nums tracking-tight">
                    {{ abs($card->daysUntilExam) }}
                </div>
                <div class="text-[10px] uppercase tracking-wider text-ink-500 mt-0.5">
                    {{ $countdownBadge['label'] ?? '日後' }}
                </div>
                <div class="text-[11px] text-ink-600 font-mono mt-0.5">{{ $card->examDate->format('Y/m/d') }}</div>
            @else
                <div class="text-xs text-ink-500">受験日 未設定</div>
            @endif
        </div>
    </header>

    @if ($card->progressRatio !== null)
        <div class="flex flex-col gap-1.5">
            <div class="flex justify-between items-baseline text-xs text-ink-600">
                <span>進捗</span>
                <span class="font-display text-lg font-bold tabular-nums tracking-tight text-ink-900">{{ $progressPercent }}%</span>
            </div>
            <div class="h-2 bg-ink-100 rounded-full overflow-hidden">
                <div class="h-full {{ $card->isPassed ? 'bg-success-500' : 'bg-primary-500' }} rounded-full" style="width: {{ $progressPercent }}%"></div>
            </div>
        </div>
    @else
        @include('dashboard._partials.empty-state', ['message' => '進捗を取得できませんでした。'])
    @endif

    <div class="flex flex-wrap gap-4 pt-3 border-t border-[var(--border-subtle)] text-xs">
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

    @if ($card->isPassed && $card->certificateDownloadUrl !== null)
        <div class="flex items-center gap-2.5 px-3.5 py-2.5 bg-success-50 border border-success-200 rounded-[10px]">
            <x-icon name="check-badge" class="w-4 h-4 text-success-700 flex-shrink-0" />
            <span class="text-xs text-success-900 flex-1 leading-relaxed">
                <b class="font-bold">修了済み</b> — 修了証 PDF をダウンロードできます。
            </span>
            <a href="{{ $card->certificateDownloadUrl }}"
               class="bg-success-600 hover:bg-success-700 text-white px-3.5 py-2 text-xs font-bold rounded-lg inline-flex items-center gap-1 transition-colors">
                <x-icon name="document-text" class="w-3 h-3" />
                修了証 PDF
            </a>
        </div>
    @elseif ($card->canReceiveCertificate)
        <form method="POST" action="{{ route('enrollments.receiveCertificate', $card->enrollmentId) }}">
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
    @else
        <p class="text-[11px] text-ink-500 flex items-center gap-1.5">
            <x-icon name="information-circle" class="w-3 h-3" />
            修了条件: 公開模試すべての合格点超え
        </p>
    @endif
</article>
