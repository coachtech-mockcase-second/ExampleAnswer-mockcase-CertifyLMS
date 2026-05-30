{{--
    模試の受験画面（受講生）。問題に解答し、最後に答案を提出する中心画面。
    構成: 集中受験用ヘッダ（退出リンク + 解答済カウンタ）→ 提出フォーム（空・ボタンから submit）→ 2 カラム（左: 問題カード一覧 / 右: sticky ナビゲーター + 提出ボタン）
    各問題カード: 番号 + 出題分野バッジ / 問題文（whitespace-pre-line）/ ラジオ選択肢（選択中はハイライト）/ 保存ステータス行
    ナビゲーター: 問番グリッド（解答済は緑、アンカーで各問へジャンプ）+ 解答済サマリ
    フロント観点: 素の JS（answer-autosave.js を @push('scripts') で読み込み）。選択肢を選ぶと data-quiz-autosave-root 配下の data 属性経由で自動保存し、解答済カウンタ（data-answered-count）/ ナビ（data-nav-item）/ 保存ステータス（data-save-status）を更新。提出は confirm() 付きフォーム送信。
--}}
@extends('layouts.app')

@section('title', $session->mockExam->title . ' を受験中')

@section('content')
    @php
        $answeredCount = $answers->count();
        $totalQuestions = $session->total_questions;
    @endphp

    {{-- 集中受験用ヘッダ(パンくず最小) --}}
    <div class="flex items-center gap-3 flex-wrap mb-4">
        <a href="{{ route('mock-exam.catalog.show', ['enrollment' => $session->enrollment, 'mockExam' => $session->mockExam]) }}"
           class="inline-flex items-center gap-1 text-xs text-ink-500 hover:text-ink-700">
            <x-icon name="arrow-left" class="w-4 h-4" />
            退出
        </a>
        <div class="h-5 w-px bg-ink-200"></div>
        <div>
            <p class="text-xs text-ink-500">{{ $session->enrollment->certification->name }} · 模擬試験</p>
            <h1 class="text-base font-bold text-ink-900">{{ $session->mockExam->title }}</h1>
        </div>
        <div class="flex-1"></div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-ink-100 text-sm font-semibold text-ink-700 tabular-nums">
            <x-icon name="check-circle" class="w-4 h-4 text-success-600" />
            <span data-answered-count>{{ $answeredCount }}</span> / {{ $totalQuestions }} 解答済
        </span>
    </div>

    <form id="mock-exam-submit-form" method="POST" action="{{ route('mock-exam-sessions.submit', $session) }}"
          onsubmit="return confirm('答案を提出して採点を実行しますか?提出後は解答を変更できません。');">
        @csrf
    </form>

    <div class="grid gap-6 lg:grid-cols-[1fr_280px]"
         data-quiz-autosave-root
         data-autosave-url="{{ route('mock-exam-sessions.answers.update', $session) }}"
         data-csrf="{{ csrf_token() }}">

        {{-- 問題リスト --}}
        <div class="space-y-6">
            @foreach ($questions as $index => $question)
                @php
                    $answer = $answers->get($question->id);
                    $selectedOptionId = $answer?->selected_option_id;
                @endphp
                <x-card padding="md" shadow="sm" id="question-{{ $question->id }}">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="text-3xl font-bold text-primary-600 tabular-nums leading-none">
                            {{ $index + 1 }}<span class="text-base text-ink-400 font-semibold"> / {{ $totalQuestions }}</span>
                        </span>
                        <div class="flex-1"></div>
                        <x-badge variant="info" size="sm">{{ $question->category?->name ?? '未分類' }}</x-badge>
                    </div>

                    <p class="text-base text-ink-900 leading-relaxed whitespace-pre-line">{{ $question->body }}</p>

                    <div class="mt-4 space-y-2.5">
                        @foreach ($question->options as $option)
                            <label class="flex items-start gap-3 p-3.5 border-2 rounded-xl cursor-pointer transition-colors
                                          {{ $selectedOptionId === $option->id ? 'border-primary-600 bg-primary-50' : 'border-ink-200 hover:border-primary-300 hover:bg-ink-50' }}">
                                <input
                                    type="radio"
                                    name="answer-{{ $question->id }}"
                                    value="{{ $option->id }}"
                                    @checked($selectedOptionId === $option->id)
                                    data-quiz-autosave
                                    data-question-id="{{ $question->id }}"
                                    data-option-id="{{ $option->id }}"
                                    class="mt-1 w-4 h-4 text-primary-600 focus:ring-primary-500"
                                >
                                <span class="text-sm text-ink-900 leading-relaxed flex-1">{{ $option->body }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-3 flex items-center gap-2 text-xs text-ink-500">
                        <x-icon name="cloud" class="w-3.5 h-3.5" />
                        <span data-save-status="{{ $question->id }}">
                            @if ($answer)
                                自動保存済 · {{ $answer->answered_at?->format('H:i:s') }}
                            @else
                                未解答
                            @endif
                        </span>
                    </div>
                </x-card>
            @endforeach
        </div>

        {{-- ナビゲーター(sticky) --}}
        <aside class="lg:sticky lg:top-4 lg:self-start space-y-4">
            <x-card padding="sm" shadow="sm">
                <p class="text-xs font-bold text-ink-900 mb-3">
                    <x-icon name="clipboard-document-check" class="w-4 h-4 inline-block text-secondary-600" />
                    問題ナビゲーター
                </p>
                <div class="grid grid-cols-5 gap-1 mb-3" data-nav-grid>
                    @foreach ($questions as $index => $question)
                        @php $isAnswered = $answers->has($question->id); @endphp
                        <a
                            href="#question-{{ $question->id }}"
                            data-nav-item="{{ $question->id }}"
                            class="aspect-square flex items-center justify-center text-xs font-semibold rounded
                                   border tabular-nums transition-colors
                                   {{ $isAnswered ? 'bg-success-100 border-success-300 text-success-800' : 'bg-white border-ink-200 text-ink-700 hover:border-primary-300' }}"
                        >{{ $index + 1 }}</a>
                    @endforeach
                </div>

                <div class="border-t pt-3 mt-3 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-ink-500">解答済</span>
                        <b class="text-ink-900 tabular-nums" data-answered-summary>{{ $answeredCount }} / {{ $totalQuestions }}</b>
                    </div>
                </div>
            </x-card>

            <x-button type="submit" form="mock-exam-submit-form" variant="primary" size="lg" class="w-full">
                <x-icon name="paper-airplane" class="w-4 h-4" />
                答案を提出する
            </x-button>
        </aside>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/mock-exam/answer-autosave.js')
@endpush
