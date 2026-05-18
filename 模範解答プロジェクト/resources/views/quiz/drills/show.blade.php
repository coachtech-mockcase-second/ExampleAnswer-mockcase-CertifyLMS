@extends('layouts.app')

@section('title', $category->name . ' ・ 苦手分野ドリル')

@php
    $firstQuestion = $questions->first();
    $solvedCount = $questions->filter(fn ($q) => $q->sectionQuestionAttempts->isNotEmpty())->count();
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '受講中資格', 'href' => route('enrollments.index')],
        ['label' => $enrollment->certification->name, 'href' => route('enrollments.show', $enrollment)],
        ['label' => '苦手分野ドリル', 'href' => route('quiz.drills.index', $enrollment)],
        ['label' => $category->name],
    ]" />

    <header class="mt-6">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary-700">出題分野</p>
        <h1 class="mt-1 font-display text-3xl font-bold tracking-tight text-ink-900">
            {{ $category->name }}
        </h1>
        <p class="mt-1.5 text-sm text-ink-600">
            出題範囲: <span class="font-semibold tabular-nums">{{ $questions->count() }}</span> 問
            ／ 挑戦済み: <span class="font-semibold tabular-nums">{{ $solvedCount }}</span> 問
        </p>
    </header>

    @if ($questions->isEmpty())
        <div class="mt-8">
            <x-empty-state
                icon="document-magnifying-glass"
                title="この分野にはまだ問題がありません"
                description="出題分野に紐づく公開済の問題が登録されると、ここから演習できます。"
            >
                <x-slot:action>
                    <x-link-button :href="route('quiz.drills.index', $enrollment)">カテゴリ一覧へ戻る</x-link-button>
                </x-slot:action>
            </x-empty-state>
        </div>
    @else
        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if ($firstQuestion)
                <x-link-button
                    :href="route('quiz.drills.question', ['enrollment' => $enrollment, 'questionCategory' => $category, 'question' => $firstQuestion])"
                    variant="primary"
                >
                    <x-icon name="play" class="w-4 h-4 mr-1.5" />
                    最初の問題から解く
                </x-link-button>
            @endif
        </div>

        <div class="mt-6 space-y-3">
            @foreach ($questions as $i => $question)
                @include('quiz.partials.question-card', [
                    'question' => $question,
                    'href' => route('quiz.drills.question', ['enrollment' => $enrollment, 'questionCategory' => $category, 'question' => $question]),
                    'index' => $i + 1,
                ])
            @endforeach
        </div>
    @endif

    <div class="mt-8">
        <a href="{{ route('quiz.drills.index', $enrollment) }}" class="inline-flex items-center gap-2 text-sm text-ink-600 hover:text-primary-700">
            <x-icon name="arrow-left" class="w-4 h-4" />
            カテゴリ一覧へ戻る
        </a>
    </div>
@endsection
