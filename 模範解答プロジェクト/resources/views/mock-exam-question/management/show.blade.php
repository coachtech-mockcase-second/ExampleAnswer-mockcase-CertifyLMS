@extends('layouts.app')

@section('title', '問題詳細 — ' . $mockExam->title)

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試マスタ管理', 'href' => route('admin.mock-exams.index')],
        ['label' => $mockExam->title, 'href' => route('admin.mock-exams.show', $mockExam)],
        ['label' => '問題セット', 'href' => route('admin.mock-exams.questions.index', $mockExam)],
        ['label' => '問題詳細'],
    ]" />

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-ink-900">問題詳細</h1>
            <p class="mt-1 text-sm text-ink-500">
                {{ $mockExam->title }} ·
                出題分野 <span class="font-semibold text-ink-700">{{ $question->category?->name ?? '未分類' }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-link-button href="{{ route('admin.mock-exam-questions.edit', $question) }}" variant="outline" size="sm">
                <x-icon name="pencil-square" class="w-4 h-4" />
                編集
            </x-link-button>
        </div>
    </div>

    <x-card class="mt-6" padding="md" shadow="sm">
        <x-slot:header>問題文</x-slot:header>
        <p class="text-sm text-ink-900 leading-relaxed whitespace-pre-line">{{ $question->body }}</p>
    </x-card>

    <x-card class="mt-4" padding="md" shadow="sm">
        <x-slot:header>選択肢</x-slot:header>
        <div class="space-y-2">
            @foreach ($question->options as $option)
                <div class="flex items-start gap-3 p-3 border-2 rounded-lg
                            {{ $option->is_correct ? 'border-success-500 bg-success-50' : 'border-ink-200' }}">
                    <span class="text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center shrink-0
                                 {{ $option->is_correct ? 'bg-success-500 text-white' : 'bg-ink-200 text-ink-700' }}">
                        {{ chr(65 + $loop->index) }}
                    </span>
                    <p class="text-sm text-ink-900 leading-relaxed flex-1">{{ $option->body }}</p>
                    @if ($option->is_correct)
                        <x-badge variant="success" size="sm">
                            <x-icon name="check" class="w-3 h-3" />
                            正答
                        </x-badge>
                    @endif
                </div>
            @endforeach
        </div>
    </x-card>

    @if ($question->explanation)
        <x-card class="mt-4" padding="md" shadow="sm">
            <x-slot:header>解説</x-slot:header>
            <p class="text-sm text-ink-700 leading-relaxed whitespace-pre-line">{{ $question->explanation }}</p>
        </x-card>
    @endif
@endsection
