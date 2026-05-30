{{--
    演習問題タブの中身（enrollments/show.blade.php の「演習問題」タブで include）。
    構成: 右上の動線（苦手分野ドリル / 解答履歴 / 問題別サマリへのリンク）
      → Part カードごとに Section 行リスト（各行: Section 名 + Chapter 名 + スコア表示〔挑戦回数 / 最高 / 最新、未挑戦バッジ〕+ 「演習へ」リンク）
    演習未公開時は empty-state。JS なし（リンク遷移のみ）。スコアは算出済みの値を描画するだけ
--}}
@php
    /** @var \Illuminate\Database\Eloquent\Collection $parts */
    /** @var \Illuminate\Support\Collection<string, \App\Services\SectionQuestionScoreSummary> $quizScoreSummaries */
    /** @var \App\Models\Enrollment $enrollment */
@endphp

{{-- タブ内右上の動線(苦手分野ドリル / 解答履歴 / 問題別サマリ) --}}
<div class="mb-4 flex flex-wrap items-center justify-end gap-2 text-xs">
    <a href="{{ route('quiz.drills.index', $enrollment) }}"
        class="inline-flex items-center gap-1 rounded-full border border-subtle bg-white px-3 py-1.5 font-semibold text-danger-700 transition-colors hover:bg-danger-50 hover:border-danger-200">
        <x-icon name="exclamation-triangle" class="w-3.5 h-3.5" />
        苦手分野ドリル
    </a>
    <a href="{{ route('quiz.history.index', $enrollment) }}"
        class="inline-flex items-center gap-1 rounded-full border border-subtle bg-white px-3 py-1.5 font-semibold text-ink-700 transition-colors hover:bg-primary-50 hover:border-primary-200 hover:text-primary-700">
        <x-icon name="clock" class="w-3.5 h-3.5" />
        解答履歴
    </a>
    <a href="{{ route('quiz.stats.index', $enrollment) }}"
        class="inline-flex items-center gap-1 rounded-full border border-subtle bg-white px-3 py-1.5 font-semibold text-ink-700 transition-colors hover:bg-primary-50 hover:border-primary-200 hover:text-primary-700">
        <x-icon name="chart-bar" class="w-3.5 h-3.5" />
        問題別サマリ
    </a>
</div>

@if ($parts->isEmpty())
    <x-empty-state
        icon="clipboard-document-check"
        title="演習問題が公開されていません"
        description="教材の各 Section に紐づく問題演習を準備中です。" />
@else
    <div class="space-y-6">
        @foreach ($parts as $part)
            <x-card padding="md" shadow="sm">
                <x-slot:header>
                    <span class="text-base font-bold text-ink-900">Part {{ $loop->iteration }} ・ {{ $part->title }}</span>
                </x-slot:header>

                <ul class="space-y-2">
                    @foreach ($part->chapters as $chapter)
                        @foreach ($chapter->sections as $section)
                            @php $summary = $quizScoreSummaries->get($section->id); @endphp
                            <li class="flex items-center justify-between gap-3 rounded-lg border border-subtle bg-surface-canvas px-4 py-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-ink-900 truncate">{{ $section->title }}</div>
                                    <div class="text-xs text-ink-500">{{ $chapter->title }}</div>
                                </div>
                                <div class="flex items-center gap-4 text-xs text-ink-500 tabular-nums whitespace-nowrap">
                                    @if ($summary !== null && $summary->attemptCount > 0)
                                        <span>挑戦 {{ $summary->attemptCount }} 回</span>
                                        <span>最高 {{ $summary->bestScore !== null ? $summary->bestScore : '-' }}</span>
                                        <span>最新 {{ $summary->latestScore !== null ? $summary->latestScore : '-' }}</span>
                                    @else
                                        <span class="italic">未挑戦</span>
                                    @endif

                                    <a href="{{ route('quiz.sections.show', $section) }}" class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-3 py-1 text-[11px] font-semibold text-primary-700 hover:bg-primary-100">
                                        演習へ
                                        <x-icon name="chevron-right" class="w-3 h-3" />
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </x-card>
        @endforeach
    </div>
@endif
