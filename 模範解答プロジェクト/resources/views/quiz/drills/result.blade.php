{{--
    苦手分野ドリルの解答結果画面。
    構成: パンくず → 問題カード（バッジ列 + 問題文 + 正誤結果 partial）→ フッタ操作（誤答時のみ「もう一度挑戦」/ 次問あれば「次の問題へ」）
    JS なし（正誤の内訳表示は quiz.partials.result-pane を include）。
--}}
@extends('layouts.app')

@section('title', '苦手分野ドリル ・ 解答結果')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '苦手分野ドリル', 'href' => route('quiz.drills.index', $enrollment)],
        ['label' => $category->name, 'href' => route('quiz.drills.category', ['enrollment' => $enrollment, 'questionCategory' => $category])],
        ['label' => '解答結果'],
    ]" />

    <div class="mt-6 mx-auto max-w-[800px] rounded-2xl border border-subtle bg-white p-7 lg:p-9 shadow-sm">
        <div class="flex items-center gap-2">
            <x-badge variant="danger" size="sm">
                <x-icon name="exclamation-triangle" class="w-3.5 h-3.5" />
                苦手分野ドリル
            </x-badge>
            <x-badge variant="info" size="sm">{{ $category->name }}</x-badge>
        </div>

        <h1 class="mt-3 font-display text-xl font-bold leading-relaxed text-ink-900">
            {{ $question->body }}
        </h1>

        @include('quiz.partials.result-pane', [
            'question' => $question,
            'answer' => $answer,
            'correctOption' => $correctOption,
            'attempt' => $attempt,
        ])
    </div>

    <div class="mt-6 mx-auto max-w-[800px] flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('quiz.drills.category', ['enrollment' => $enrollment, 'questionCategory' => $category]) }}" class="inline-flex items-center gap-2 text-sm text-ink-600 hover:text-primary-700">
            <x-icon name="arrow-left" class="w-4 h-4" />
            カテゴリリストへ戻る
        </a>

        <div class="flex flex-wrap items-center gap-3">
            @if (! $answer->is_correct)
                <x-link-button
                    :href="route('quiz.drills.question', ['enrollment' => $enrollment, 'questionCategory' => $category, 'question' => $question])"
                    variant="outline"
                >
                    <x-icon name="arrow-path" class="w-4 h-4 mr-1.5" />
                    もう一度挑戦する
                </x-link-button>
            @endif

            @if ($nextId)
                <x-link-button
                    :href="route('quiz.drills.question', ['enrollment' => $enrollment, 'questionCategory' => $category, 'question' => $nextId])"
                    variant="primary"
                >
                    次の問題へ
                    <x-icon name="arrow-right" class="w-4 h-4 ml-1.5" />
                </x-link-button>
            @endif
        </div>
    </div>
@endsection
