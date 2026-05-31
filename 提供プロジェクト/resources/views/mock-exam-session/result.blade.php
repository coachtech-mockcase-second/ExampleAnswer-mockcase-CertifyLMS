{{--
    模試の採点結果画面（受講生）。合否・スコア・弱点分析・次アクションをまとめて見せる。
    構成: パンくず → ヒーロー（合否で配色変化・スコア・正解数・合格点・問題数）→ 2 カラム
    左カラム: 弱点ヒートマップ（分野別の正答率バー）/ 解答の正誤一覧（問ごとの正答・誤答・未解答バッジ）
    右カラム: 合格可能性スコア（バンドバッジ + 3 段階インジケータ）/ 苦手分野ドリル（合格点未達の分野リスト + ドリルへのリンク、ルート有無で表示）/ アクション（もう一度受験フォーム・履歴へ戻る）
    フロント観点: JS なし（静的な結果表示 + リンク / フォーム POST）。$cellColor ヘルパは正答率を色クラスへ割り当てる。ヒートマップバー幅は style で算出値を反映。
--}}
@extends('layouts.app')

@section('title', $session->mockExam->title . ' の結果')

@php
    use App\Enums\PassProbabilityBand;

    $passed = $session->pass === true;
    $heroGradient = $passed
        ? 'bg-gradient-to-br from-success-400 via-success-300 to-info-300 text-success-900'
        : 'bg-gradient-to-br from-warning-300 via-danger-300 to-danger-500 text-white';

    $cellColor = function (float $rate) {
        return match (true) {
            $rate >= 70 => ['bar' => 'bg-success-500', 'text' => 'text-success-700'],
            $rate >= 50 => ['bar' => 'bg-warning-500', 'text' => 'text-warning-700'],
            default => ['bar' => 'bg-danger-500', 'text' => 'text-danger-700'],
        };
    };

    $bandLabel = $passProbabilityBand->label();
    $bandVariant = $passProbabilityBand->color();
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '受験履歴', 'href' => route('mock-exam-sessions.index')],
        ['label' => $session->mockExam->title . ' の結果'],
    ]" />

    {{-- ヒーロー(合否 + スコア) --}}
    <div class="mt-4 {{ $heroGradient }} rounded-3xl p-7 relative overflow-hidden">
        <div class="relative z-10">
            <p class="text-xs font-bold uppercase tracking-wider opacity-75">
                {{ $session->graded_at?->format('Y/m/d H:i') }} 採点完了
            </p>
            <h1 class="mt-1 text-2xl font-bold leading-tight">
                {{ $session->mockExam->title }}
                @if ($passed)
                    <span class="inline-flex items-center gap-1 ml-3 px-3 py-1 rounded-full bg-white/30 text-sm font-bold">
                        <x-icon name="check-circle" class="w-4 h-4" />
                        合格点 突破
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 ml-3 px-3 py-1 rounded-full bg-white/30 text-sm font-bold">
                        <x-icon name="x-circle" class="w-4 h-4" />
                        合格点未達
                    </span>
                @endif
            </h1>
            <div class="mt-5 flex items-end gap-7 flex-wrap">
                <div>
                    <p class="text-6xl font-bold tabular-nums leading-none">
                        {{ rtrim(rtrim((string) $session->score_percentage, '0'), '.') }}<span class="text-2xl opacity-70">%</span>
                    </p>
                    <p class="mt-1 text-base font-semibold opacity-90">
                        {{ $session->total_correct }} / {{ $session->total_questions }} 正解
                    </p>
                </div>
                <div class="flex gap-7 ml-auto opacity-90 text-sm">
                    <div>
                        <p class="text-xs uppercase tracking-wider opacity-75">合格点</p>
                        <p class="mt-0.5 text-lg font-bold tabular-nums">{{ $session->passing_score_snapshot }}%</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wider opacity-75">問題数</p>
                        <p class="mt-0.5 text-lg font-bold tabular-nums">{{ $session->total_questions }} 問</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-[2fr_1fr]">
        {{-- 左カラム: ヒートマップ + 履歴 --}}
        <div class="space-y-5">
            {{-- 弱点ヒートマップ --}}
            <x-card padding="md" shadow="sm">
                <x-slot:header>
                    <span class="inline-flex items-center gap-2">
                        <x-icon name="fire" class="w-4 h-4 text-danger-600" />
                        分野別正答率 — 弱点ヒートマップ
                    </span>
                </x-slot:header>

                @if ($heatmap->isEmpty())
                    <p class="text-sm text-ink-500 py-6 text-center">採点済みデータがありません。</p>
                @else
                    <div class="space-y-3">
                        @foreach ($heatmap as $cell)
                            @php $color = $cellColor($cell->correctRate); @endphp
                            <div class="grid grid-cols-[1fr_220px_50px_70px] items-center gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-ink-900">{{ $cell->categoryName }}</p>
                                    <p class="text-xs text-ink-500">{{ $cell->totalCount }} 問中</p>
                                </div>
                                <div class="h-3 bg-ink-100 rounded-full overflow-hidden">
                                    <div class="{{ $color['bar'] }} h-full rounded-full"
                                         style="width: {{ max(min((float) $cell->correctRate, 100), 2) }}%"></div>
                                </div>
                                <span class="text-xs font-mono text-ink-500 text-right">
                                    {{ $cell->correctCount }}/{{ $cell->totalCount }}
                                </span>
                                <span class="text-lg font-bold tabular-nums text-right {{ $color['text'] }}">
                                    {{ (int) round($cell->correctRate) }}%
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            {{-- 解答振り返り(問題ごとの正誤一覧) --}}
            <x-card padding="md" shadow="sm">
                <x-slot:header>
                    <span class="inline-flex items-center gap-2">
                        <x-icon name="document-magnifying-glass" class="w-4 h-4 text-info-600" />
                        解答の正誤
                    </span>
                </x-slot:header>
                <div class="space-y-2">
                    @foreach ($questions as $index => $question)
                        @php
                            $answer = $answers->get($question->id);
                            $isCorrect = $answer?->is_correct ?? false;
                        @endphp
                        <div class="flex items-center gap-3 py-2 px-3 rounded-lg border border-ink-100
                                    {{ $isCorrect ? 'bg-success-50' : 'bg-danger-50' }}">
                            <span class="text-xs font-mono text-ink-500 tabular-nums">#{{ $index + 1 }}</span>
                            <span class="text-sm text-ink-900 line-clamp-1 flex-1">{{ $question->body }}</span>
                            @if ($answer === null)
                                <x-badge variant="gray" size="sm">未解答</x-badge>
                            @elseif ($isCorrect)
                                <x-badge variant="success" size="sm">
                                    <x-icon name="check" class="w-3.5 h-3.5" />
                                    正答
                                </x-badge>
                            @else
                                <x-badge variant="danger" size="sm">
                                    <x-icon name="x-mark" class="w-3.5 h-3.5" />
                                    誤答
                                </x-badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>

        {{-- 右カラム: 合格可能性 + 苦手分野ドリル + アクション --}}
        <div class="space-y-5">
            {{-- 合格可能性 --}}
            <x-card padding="md" shadow="sm">
                <x-slot:header>
                    <span class="inline-flex items-center gap-2">
                        <x-icon name="arrow-trending-up" class="w-4 h-4 text-primary-600" />
                        合格可能性スコア
                    </span>
                </x-slot:header>
                <div class="flex items-center gap-3 mb-4">
                    <x-badge :variant="$bandVariant" size="md">{{ $bandLabel }}</x-badge>
                    <span class="text-xs text-ink-500">直近 3 回の平均得点率で評価</span>
                </div>
                <div class="flex gap-1.5">
                    <div class="flex-1 h-2 rounded {{ $passProbabilityBand === PassProbabilityBand::Danger ? 'bg-danger-500' : 'bg-ink-100' }}"></div>
                    <div class="flex-1 h-2 rounded {{ in_array($passProbabilityBand, [PassProbabilityBand::Warning, PassProbabilityBand::Safe], true) ? 'bg-warning-500' : 'bg-ink-100' }}"></div>
                    <div class="flex-1 h-2 rounded {{ $passProbabilityBand === PassProbabilityBand::Safe ? 'bg-success-500' : 'bg-ink-100' }}"></div>
                </div>
                <div class="flex justify-between text-[10px] text-ink-500 mt-1.5 tabular-nums">
                    <span>低 (70% 未満)</span>
                    <span>中 (70-90%)</span>
                    <span>高 (90% 以上)</span>
                </div>
            </x-card>

            {{-- 苦手分野ドリル --}}
            @php
                $weakCategories = $heatmap->filter(fn ($c) => $c->correctRate < $session->passing_score_snapshot);
            @endphp
            @if ($weakCategories->isNotEmpty() && \Illuminate\Support\Facades\Route::has('quiz.drills.index'))
                <x-card padding="md" shadow="sm">
                    <x-slot:header>
                        <span class="inline-flex items-center gap-2">
                            <x-icon name="sparkles" class="w-4 h-4 text-warning-600" />
                            苦手分野ドリル
                        </span>
                    </x-slot:header>
                    <p class="text-xs text-ink-500 mb-3">
                        以下の分野が合格点を下回りました。集中的にドリル演習することで弱点を克服できます。
                    </p>
                    <div class="space-y-2">
                        @foreach ($weakCategories as $cell)
                            <div class="flex items-center justify-between py-2 px-3 bg-warning-50 rounded-lg">
                                <span class="text-sm font-semibold text-ink-900">{{ $cell->categoryName }}</span>
                                <span class="text-sm tabular-nums text-warning-700 font-bold">
                                    {{ (int) round($cell->correctRate) }}%
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <x-link-button
                        href="{{ route('quiz.drills.index', $session->enrollment) }}"
                        variant="primary"
                        size="sm"
                        class="w-full mt-4"
                    >
                        <x-icon name="play" class="w-4 h-4" />
                        苦手分野ドリルへ
                    </x-link-button>
                </x-card>
            @endif

            {{-- アクション --}}
            <x-card padding="md" shadow="sm">
                <div class="space-y-2">
                    <form novalidate method="POST" action="{{ route('mock-exam.sessions.store', ['enrollment' => $session->enrollment, 'mockExam' => $session->mockExam]) }}">
                        @csrf
                        <x-button type="submit" variant="outline" size="md" class="w-full">
                            <x-icon name="arrow-path" class="w-4 h-4" />
                            もう一度受験する
                        </x-button>
                    </form>
                    <x-link-button href="{{ route('mock-exam-sessions.index') }}" variant="ghost" size="md" class="w-full">
                        受験履歴へ戻る
                    </x-link-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
