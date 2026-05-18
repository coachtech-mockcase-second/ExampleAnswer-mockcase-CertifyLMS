@php
    /** @var \Illuminate\Database\Eloquent\Collection $parts */
    /** @var array<string, mixed> $quizScoreSummaries */
@endphp

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
                            @php $summary = $quizScoreSummaries[$section->id] ?? null; @endphp
                            <li class="flex items-center justify-between gap-3 rounded-lg border border-[var(--border-subtle)] bg-surface-canvas px-4 py-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-ink-900 truncate">{{ $section->title }}</div>
                                    <div class="text-xs text-ink-500">{{ $chapter->title }}</div>
                                </div>
                                <div class="flex items-center gap-4 text-xs text-ink-500 tabular-nums whitespace-nowrap">
                                    @if ($summary !== null)
                                        <span>挑戦 {{ $summary['attempts'] ?? 0 }} 回</span>
                                        <span>最高 {{ $summary['best_score'] ?? '-' }}</span>
                                    @else
                                        <span class="italic">未受験</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </x-card>
        @endforeach
    </div>
@endif
