@extends('layouts.app')

@section('title', $mockExam->title . ' — 問題セット')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試マスタ管理', 'href' => route('admin.mock-exams.index')],
        ['label' => $mockExam->title, 'href' => route('admin.mock-exams.show', $mockExam)],
        ['label' => '問題セット'],
    ]" />

    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">問題セット</h1>
            <p class="mt-1 text-sm text-ink-500">
                {{ $mockExam->title }}({{ $mockExam->certification->name }})の問題を編集します。
                <span class="font-semibold text-ink-700 tabular-nums">{{ $questions->count() }} 件</span>
            </p>
        </div>
        <x-link-button href="{{ route('admin.mock-exams.questions.create', $mockExam) }}" variant="primary">
            <x-icon name="plus" class="w-4 h-4" />
            問題を追加
        </x-link-button>
    </div>

    <div class="mt-6">
        @if ($questions->isEmpty())
            <x-empty-state
                icon="document-text"
                title="問題がまだ登録されていません"
                description="「問題を追加」ボタンから最初の問題を作成してください。"
            />
        @else
            <div class="space-y-3">
                @foreach ($questions as $index => $question)
                    <x-card padding="md" shadow="sm">
                        <div class="flex items-start gap-4">
                            <span class="text-2xl font-bold text-primary-600 tabular-nums leading-none mt-1">
                                #{{ $index + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-2">
                                    <x-badge variant="info" size="sm">{{ $question->category?->name ?? '未分類' }}</x-badge>
                                    <span class="text-xs text-ink-500 tabular-nums">order: {{ $question->order }}</span>
                                </div>
                                <p class="text-sm text-ink-900 leading-relaxed line-clamp-2">{{ $question->body }}</p>
                                <p class="mt-2 text-xs text-ink-500">
                                    選択肢 <span class="tabular-nums font-semibold">{{ $question->options->count() }}</span> 件
                                    @php $correctCount = $question->options->where('is_correct', true)->count(); @endphp
                                    @if ($correctCount === 1)
                                        · <span class="text-success-700">正答 1 件</span>
                                    @else
                                        · <span class="text-danger-700">正答 {{ $correctCount }} 件(要修正)</span>
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <x-link-button href="{{ route('admin.mock-exam-questions.show', $question) }}" variant="ghost" size="sm">詳細</x-link-button>
                                <x-link-button href="{{ route('admin.mock-exam-questions.edit', $question) }}" variant="outline" size="sm">
                                    <x-icon name="pencil-square" class="w-4 h-4" />
                                </x-link-button>
                                <form method="POST" action="{{ route('admin.mock-exam-questions.destroy', $question) }}"
                                      onsubmit="return confirm('この問題を削除しますか?過去のセッションには影響しません(snapshot)。');">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" variant="ghost" size="sm" class="text-danger-600">
                                        <x-icon name="trash" class="w-4 h-4" />
                                    </x-button>
                                </form>
                            </div>
                        </div>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
@endsection
