@props([
    'question',
    'answer',
    'correctOption' => null,
    'attempt' => null,
])

@php
    $isCorrect = $answer->is_correct;
    $accuracy = $attempt?->accuracy();
    $options = $question->options;
@endphp

<div class="mt-6 space-y-6">
    <div class="flex items-center gap-3 rounded-2xl border {{ $isCorrect ? 'border-success-200 bg-success-50' : 'border-danger-200 bg-danger-50' }} px-5 py-4">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full {{ $isCorrect ? 'bg-success-600' : 'bg-danger-600' }} text-white">
            <x-icon :name="$isCorrect ? 'check' : 'x-mark'" class="w-6 h-6" />
        </span>
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider {{ $isCorrect ? 'text-success-700' : 'text-danger-700' }}">
                {{ $isCorrect ? '正解' : '不正解' }}
            </p>
            <p class="text-base font-bold text-ink-900">
                {{ $isCorrect ? 'よくできました!' : 'もう一度確認しましょう' }}
            </p>
        </div>
    </div>

    <div class="space-y-2.5">
        @foreach ($options as $index => $option)
            @php
                $key = chr(65 + $index);
                $isSelected = $option->id === $answer->selected_option_id;
                $isAnswer = (bool) $option->is_correct;

                $variant = match (true) {
                    $isSelected && $isAnswer => 'correct',
                    $isSelected && ! $isAnswer => 'wrong',
                    ! $isSelected && $isAnswer => 'is-answer',
                    default => 'neutral',
                };

                $borderBg = match ($variant) {
                    'correct' => 'border-success-500 bg-success-50',
                    'wrong' => 'border-danger-400 bg-danger-50',
                    'is-answer' => 'border-success-400 bg-success-50',
                    default => 'border-[var(--border-subtle)] bg-white',
                };

                $keyBg = match ($variant) {
                    'correct' => 'bg-success-500 text-white',
                    'wrong' => 'bg-danger-500 text-white',
                    'is-answer' => 'bg-success-500 text-white',
                    default => 'bg-ink-100 text-ink-700',
                };
            @endphp
            <div class="flex items-start gap-3.5 rounded-2xl border px-4 py-3.5 {{ $borderBg }}">
                <span class="inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full font-display text-sm font-bold {{ $keyBg }}">{{ $key }}</span>
                <span class="flex-1 text-sm leading-relaxed text-ink-900">{{ $option->body }}</span>
                @if ($variant === 'correct' || $variant === 'is-answer')
                    <x-icon name="check-circle" class="w-5 h-5 flex-shrink-0 text-success-600" />
                @elseif ($variant === 'wrong')
                    <x-icon name="x-circle" class="w-5 h-5 flex-shrink-0 text-danger-600" />
                @endif
            </div>
        @endforeach
    </div>

    @if ($question->explanation)
        <div class="rounded-2xl border border-primary-200 bg-primary-50 px-5 py-4">
            <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-primary-800">
                <x-icon name="information-circle" class="w-4 h-4" />
                解説
            </div>
            <p class="mt-2 text-sm leading-relaxed text-ink-900 whitespace-pre-wrap">{{ $question->explanation }}</p>
        </div>
    @endif

    @if ($attempt)
        <div class="rounded-2xl border border-[var(--border-subtle)] bg-white px-5 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-500">この問題の累計</p>
            <dl class="mt-2 grid grid-cols-3 gap-3 text-center">
                <div>
                    <dt class="text-[10px] text-ink-500">試行回数</dt>
                    <dd class="mt-0.5 font-display text-lg font-bold tabular-nums text-ink-900">{{ $attempt->attempt_count }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] text-ink-500">正解回数</dt>
                    <dd class="mt-0.5 font-display text-lg font-bold tabular-nums text-success-700">{{ $attempt->correct_count }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] text-ink-500">正答率</dt>
                    <dd class="mt-0.5 font-display text-lg font-bold tabular-nums text-primary-700">
                        {{ $accuracy !== null ? number_format($accuracy * 100, 1) . '%' : '—' }}
                    </dd>
                </div>
            </dl>
        </div>
    @endif
</div>
