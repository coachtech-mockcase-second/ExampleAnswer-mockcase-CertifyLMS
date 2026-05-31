{{--
    セクション紐づき問題演習の問題リスト画面。
    構成: パンくず → ヘッダ（このセクションの問数・挑戦済み数）→ 操作ボタン群（最初から / 未解答から / 全部やり直す + 全問正解バッジ）→ 問題カード一覧（question-card partial）／ 0 件時は空状態 → セクション詳細へ戻る
    JS なし（各カードはリンク）。挑戦済み数・全問正解判定は表示要素。
--}}
@extends('layouts.app')

@section('title', $section->title . ' ・ 問題演習')

@php
    $chapter = $section->chapter;
    $part = $chapter?->part;
    $certification = $part?->certification;
    $publishedQuestions = $section->questions->where('status', \App\Enums\ContentStatus::Published)->values();
    $totalQuestions = $publishedQuestions->count();
    $solvedCount = $publishedQuestions->filter(fn ($q) => $q->sectionQuestionAttempts->isNotEmpty())->count();
    $allCorrect = $totalQuestions > 0
        && $publishedQuestions->every(fn ($q) => $q->sectionQuestionAttempts->first()?->last_is_correct === true);
    $firstQuestion = $publishedQuestions->first();
    $firstUnansweredQuestion = $publishedQuestions->first(fn ($q) => $q->sectionQuestionAttempts->isEmpty());
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '教材・演習', 'href' => route('learning.index')],
        $certification ? ['label' => $certification->name] : null,
        $part ? ['label' => $part->title, 'href' => route('learning.parts.show', $part)] : null,
        $chapter ? ['label' => $chapter->title, 'href' => route('learning.chapters.show', $chapter)] : null,
        ['label' => $section->title, 'href' => route('learning.sections.show', $section)],
        ['label' => '問題演習'],
    ]" />

    <header class="mt-6">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary-700">SECTION 紐づき問題演習</p>
        <h1 class="mt-1 font-display text-3xl font-bold tracking-tight text-ink-900">
            {{ $section->title }}
        </h1>
        <p class="mt-1.5 text-sm text-ink-600">
            このセクションに紐づく問題 <span class="font-semibold tabular-nums">{{ $totalQuestions }}</span> 問のうち、
            <span class="font-semibold tabular-nums">{{ $solvedCount }}</span> 問挑戦済みです。
        </p>
    </header>

    @if ($totalQuestions === 0)
        <div class="mt-8">
            <x-empty-state
                icon="document-magnifying-glass"
                title="まだ問題が登録されていません"
                description="このセクションには公開済みの演習問題がありません。教材本文に戻って学習を進めましょう。"
            >
                <x-slot:action>
                    <x-link-button :href="route('learning.sections.show', $section)">セクションへ戻る</x-link-button>
                </x-slot:action>
            </x-empty-state>
        </div>
    @else
        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if ($firstQuestion)
                <x-link-button
                    :href="route('quiz.sections.question', ['section' => $section, 'question' => $firstQuestion])"
                    variant="primary"
                >
                    <x-icon name="play" class="w-4 h-4 mr-1.5" />
                    最初から順に解く
                </x-link-button>
            @endif

            @if ($firstUnansweredQuestion)
                <x-link-button
                    :href="route('quiz.sections.question', ['section' => $section, 'question' => $firstUnansweredQuestion])"
                    variant="outline"
                >
                    未解答の問題から解く
                </x-link-button>
            @endif

            @if ($firstQuestion)
                <x-link-button
                    :href="route('quiz.sections.question', ['section' => $section, 'question' => $firstQuestion])"
                    variant="ghost"
                >
                    全部やり直す
                </x-link-button>
            @endif

            @if ($allCorrect)
                <x-badge variant="success" size="md">
                    <x-icon name="check-circle" class="w-3.5 h-3.5" />
                    全問正解
                </x-badge>
            @endif
        </div>

        <div class="mt-6 space-y-3">
            @foreach ($publishedQuestions as $i => $question)
                @include('quiz.partials.question-card', [
                    'question' => $question,
                    'href' => route('quiz.sections.question', ['section' => $section, 'question' => $question]),
                    'index' => $i + 1,
                ])
            @endforeach
        </div>
    @endif

    <div class="mt-8">
        <a href="{{ route('learning.sections.show', $section) }}" class="inline-flex items-center gap-2 text-sm text-ink-600 hover:text-primary-700">
            <x-icon name="arrow-left" class="w-4 h-4" />
            セクション詳細へ戻る
        </a>
    </div>
@endsection
