@extends('layouts.app')

@section('title', '苦手分野ドリル ・ 出題')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '苦手分野ドリル', 'href' => route('quiz.drills.index', $enrollment)],
        ['label' => $category->name, 'href' => route('quiz.drills.category', ['enrollment' => $enrollment, 'questionCategory' => $category])],
        ['label' => '出題'],
    ]" />

    <article class="mt-6 mx-auto max-w-[800px] rounded-2xl border border-[var(--border-subtle)] bg-white p-7 lg:p-9 shadow-sm">
        <div class="flex items-center gap-2">
            <x-badge variant="danger" size="sm">
                <x-icon name="exclamation-triangle" class="w-3.5 h-3.5" />
                苦手分野ドリル
            </x-badge>
            <x-badge variant="info" size="sm">{{ $category->name }}</x-badge>
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
            'source' => \App\Enums\AnswerSource::WeakDrill->value,
            'enrollmentId' => $enrollment->id,
            'questionCategoryId' => $category->id,
        ])
    </article>

    <div class="mt-6 mx-auto max-w-[800px] flex items-center justify-between text-sm">
        <a href="{{ route('quiz.drills.category', ['enrollment' => $enrollment, 'questionCategory' => $category]) }}" class="inline-flex items-center gap-2 text-ink-600 hover:text-primary-700">
            <x-icon name="arrow-left" class="w-4 h-4" />
            カテゴリリストへ戻る
        </a>
    </div>
@endsection
