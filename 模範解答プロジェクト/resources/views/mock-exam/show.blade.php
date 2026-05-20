@extends('layouts.app')

@section('title', $mockExam->title)

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試一覧', 'href' => route('mock-exam.catalog.index', $enrollment)],
        ['label' => $mockExam->title],
    ]" />

    <div class="mt-6 max-w-3xl">
        <h1 class="text-2xl font-bold text-ink-900">{{ $mockExam->title }}</h1>
        <p class="mt-1 text-sm text-ink-500">{{ $enrollment->certification->name }}</p>

        @if ($mockExam->description)
            <div class="mt-4 p-4 bg-ink-50 rounded-lg text-sm text-ink-700 leading-relaxed">
                {{ $mockExam->description }}
            </div>
        @endif

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <x-card padding="sm" shadow="sm">
                <p class="text-xs text-ink-500">問題数</p>
                <p class="mt-1 text-xl font-bold text-ink-900 tabular-nums">
                    {{ $mockExam->mock_exam_questions_count ?? 0 }} 問
                </p>
            </x-card>
            <x-card padding="sm" shadow="sm">
                <p class="text-xs text-ink-500">合格点</p>
                <p class="mt-1 text-xl font-bold text-ink-900 tabular-nums">{{ $mockExam->passing_score }}%</p>
            </x-card>
            <x-card padding="sm" shadow="sm">
                <p class="text-xs text-ink-500">時間制限</p>
                <p class="mt-1 text-xl font-bold text-ink-900">なし</p>
            </x-card>
        </div>

        <div class="mt-8 flex flex-wrap items-center gap-3">
            @if ($activeSession)
                <x-link-button href="{{ route('mock-exam-sessions.show', $activeSession) }}" variant="primary">
                    <x-icon name="play" class="w-4 h-4" />
                    受験を再開する
                </x-link-button>
                <p class="text-xs text-ink-500">
                    進行中セッションあり ({{ $activeSession->status->label() }})。続きから再開できます。
                </p>
            @else
                <form method="POST" action="{{ route('mock-exam.sessions.store', ['enrollment' => $enrollment, 'mockExam' => $mockExam]) }}">
                    @csrf
                    <x-button type="submit" variant="primary">
                        <x-icon name="play-circle" class="w-4 h-4" />
                        新しいセッションを開始する
                    </x-button>
                </form>
                <p class="text-xs text-ink-500">時間制限はありません。明示提出で採点されます。</p>
            @endif
        </div>
    </div>
@endsection
