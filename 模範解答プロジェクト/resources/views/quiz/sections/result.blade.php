{{--
    セクション紐づき問題演習の解答結果画面。
    構成: パンくず → 問題カード（バッジ列 + 問題文 + 正誤結果 partial）→ フッタ操作（誤答時のみ「もう一度挑戦」/ 次問あれば「次の問題へ」）
    JS なし（正誤の内訳表示は quiz.partials.result-pane を include）。
--}}
@extends('layouts.app')

@section('title', '解答結果 ・ ' . $section->title)

@php
    $chapter = $section->chapter;
    $part = $chapter?->part;
    $certification = $part?->certification;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '教材・演習', 'href' => route('learning.index')],
        $certification ? ['label' => $certification->name] : null,
        ['label' => $section->title, 'href' => route('learning.sections.show', $section)],
        ['label' => '問題演習', 'href' => route('quiz.sections.show', $section)],
        ['label' => '解答結果'],
    ]" />

    <div class="mt-6 mx-auto max-w-[800px] rounded-2xl border border-subtle bg-white p-7 lg:p-9 shadow-sm">
        <div class="flex items-center gap-2">
            <x-badge variant="primary" size="sm">SECTION 演習</x-badge>
            @if ($question->category)
                <x-badge variant="info" size="sm">{{ $question->category->name }}</x-badge>
            @endif
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
        <a href="{{ route('quiz.sections.show', $section) }}" class="inline-flex items-center gap-2 text-sm text-ink-600 hover:text-primary-700">
            <x-icon name="arrow-left" class="w-4 h-4" />
            セクション エントリへ戻る
        </a>

        <div class="flex flex-wrap items-center gap-3">
            @if (! $answer->is_correct)
                <x-link-button
                    :href="route('quiz.sections.question', ['section' => $section, 'question' => $question])"
                    variant="outline"
                >
                    <x-icon name="arrow-path" class="w-4 h-4 mr-1.5" />
                    もう一度挑戦する
                </x-link-button>
            @endif

            @if ($nextId)
                <x-link-button
                    :href="route('quiz.sections.question', ['section' => $section, 'question' => $nextId])"
                    variant="primary"
                >
                    次の問題へ
                    <x-icon name="arrow-right" class="w-4 h-4 ml-1.5" />
                </x-link-button>
            @endif
        </div>
    </div>
@endsection
