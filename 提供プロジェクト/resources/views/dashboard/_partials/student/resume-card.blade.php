{{--
    前回の続きカード。最後に開いた教材（読了済なら次の未読 Section）へ 1 タップで戻る導線。
    構成: 資格名 › Part › Chapter のパンくず → Section タイトル → 「続きから」。カード全体が遷移リンク（JS 不要）。
--}}
@props([
    'resume',
])

<a href="{{ $resume->sectionUrl }}"
   class="group block bg-surface-raised border border-subtle rounded-2xl px-4 py-3.5 shadow-sm hover:border-primary-300 hover:shadow transition-all">
    <div class="flex items-center gap-3">
        <span class="inline-flex w-10 h-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
            <x-icon name="play-circle" class="w-5 h-5" />
        </span>
        <div class="flex-1 min-w-0">
            <p class="text-[11px] text-ink-500 truncate">
                {{ $resume->certificationName }}
                <span class="text-ink-300">›</span> {{ $resume->partTitle }}
                <span class="text-ink-300">›</span> {{ $resume->chapterTitle }}
            </p>
            <p class="font-display text-base font-bold text-ink-900 tracking-tight truncate group-hover:text-primary-700 transition-colors">
                {{ $resume->sectionTitle }}
            </p>
        </div>
        <span class="flex-shrink-0 inline-flex items-center gap-1 text-xs font-bold text-primary-700">
            続きから
            <x-icon name="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-0.5" />
        </span>
    </div>
</a>
