{{--
    セクション紐づき問題演習の出題画面（1 問表示）。
    構成: パンくず → 問題カード（バッジ列 + 最新正誤・挑戦回数 + 問題文 + 解答フォーム partial）→ セクション エントリへ戻るリンク
    JS なし（解答フォームは quiz.partials.answer-form を include）。
--}}
@extends('layouts.app')

@section('title', '問題演習 ・ ' . $section->title)

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
        ['label' => '出題'],
    ]" />

    <article class="mt-6 mx-auto max-w-[800px] rounded-2xl border border-subtle bg-white p-7 lg:p-9 shadow-sm">
        <div class="flex items-center gap-2">
            <x-badge variant="primary" size="sm">SECTION 演習</x-badge>
            @if ($question->category)
                <x-badge variant="info" size="sm">{{ $question->category->name }}</x-badge>
            @endif
            @if ($attempt)
                <x-badge :variant="$attempt->last_is_correct ? 'success' : 'danger'" size="sm">
                    最新: {{ $attempt->last_is_correct ? '正解' : '誤答' }}
                </x-badge>
                <span class="text-[11px] text-ink-500">これまで {{ $attempt->attempt_count }} 回挑戦</span>
            @endif
        </div>

        <h1 class="mt-3 font-display text-xl font-bold leading-relaxed text-ink-900">
            {{ $question->body }}
        </h1>

        @include('quiz.partials.answer-form', [
            'question' => $question,
            'source' => \App\Enums\AnswerSource::SectionQuiz->value,
            'sectionId' => $section->id,
        ])
    </article>

    <div class="mt-6 mx-auto max-w-[800px] flex items-center justify-between text-sm">
        <a href="{{ route('quiz.sections.show', $section) }}" class="inline-flex items-center gap-2 text-ink-600 hover:text-primary-700">
            <x-icon name="arrow-left" class="w-4 h-4" />
            セクション エントリへ戻る
        </a>
    </div>
@endsection
