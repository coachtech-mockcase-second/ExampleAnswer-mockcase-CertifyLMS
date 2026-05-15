@extends('layouts.app')

@section('title', '問題詳細')

@php
    use App\Enums\ContentStatus;
    use App\Enums\QuestionDifficulty;
    $isDraft = $question->status === ContentStatus::Draft;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => $question->certification->name, 'href' => route('admin.certifications.show', $question->certification)],
        ['label' => '問題管理', 'href' => route('admin.certifications.questions.index', $question->certification)],
        ['label' => '問題詳細'],
    ]" />

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-ink-900 line-clamp-2">{{ \Illuminate\Support\Str::limit($question->body, 80) }}</h1>
            <div class="mt-2 flex items-center gap-2">
                @include('admin.contents._partials.status-pill', ['status' => $question->status])
                <x-badge variant="info" size="sm">{{ $question->difficulty->label() }}</x-badge>
                <span class="text-xs text-ink-500">カテゴリ: {{ $question->category?->name ?? '—' }}</span>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if ($isDraft)
                <x-button variant="primary" size="sm" data-modal-trigger="question-publish-modal">
                    <x-icon name="arrow-up-on-square" class="w-4 h-4" />
                    公開
                </x-button>
                <x-button variant="danger" size="sm" data-modal-trigger="question-delete-modal">
                    <x-icon name="trash" class="w-4 h-4" />
                    削除
                </x-button>
            @else
                <x-button variant="outline" size="sm" data-modal-trigger="question-unpublish-modal">
                    <x-icon name="arrow-uturn-left" class="w-4 h-4" />
                    下書きに戻す
                </x-button>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.questions.update', $question) }}" class="mt-6 space-y-6">
        @csrf
        @method('PATCH')

        <x-card padding="md">
            <h2 class="text-sm font-semibold text-ink-700 uppercase tracking-wide">問題本文 / 解説</h2>
            <div class="mt-4 space-y-4">
                <x-form.textarea
                    name="body"
                    label="問題文"
                    :rows="4"
                    :value="old('body', $question->body)"
                    :error="$errors->first('body')"
                    :required="true"
                    :maxlength="5000"
                />
                <x-form.textarea
                    name="explanation"
                    label="解説"
                    :rows="3"
                    :value="old('explanation', $question->explanation)"
                    :error="$errors->first('explanation')"
                    :maxlength="5000"
                />
                <div class="grid gap-4 sm:grid-cols-2">
                    @include('admin.contents.questions._partials.category-select', [
                        'categories' => $categories,
                        'selected' => old('category_id', $question->category_id),
                    ])
                    <x-form.select
                        name="difficulty"
                        label="難易度"
                        :options="collect(QuestionDifficulty::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()])->toArray()"
                        :value="old('difficulty', $question->difficulty->value)"
                        :error="$errors->first('difficulty')"
                        :required="true"
                    />
                </div>
                <x-form.input
                    name="section_id"
                    label="紐づき Section ID (任意、空欄なら mock-exam 専用)"
                    :value="old('section_id', $question->section_id)"
                    :error="$errors->first('section_id')"
                />
            </div>
        </x-card>

        <x-card padding="md">
            @include('admin.contents.questions._partials.option-fieldset', [
                'options' => old('options', $question->options->map(fn ($o) => [
                    'body' => $o->body,
                    'is_correct' => $o->is_correct,
                ])->toArray()),
            ])
        </x-card>

        <div class="flex justify-end gap-2">
            <x-link-button href="{{ route('admin.certifications.questions.index', $question->certification) }}" variant="ghost">一覧へ戻る</x-link-button>
            <x-button type="submit" variant="primary">保存</x-button>
        </div>
    </form>

    @if ($isDraft)
        @include('admin.contents._modals.publish-confirm', [
            'id' => 'question-publish-modal',
            'title' => '問題を公開しますか？',
            'description' => '公開には選択肢 2 件以上 + 正答 1 件が必要です。',
            'action' => route('admin.questions.publish', $question),
        ])
        @include('admin.contents._modals.delete-confirm', [
            'id' => 'question-delete-modal',
            'title' => '問題を削除しますか？',
            'description' => '問題を SoftDelete します。mock-exam で参照中の場合は削除できません。',
            'action' => route('admin.questions.destroy', $question),
        ])
    @else
        @include('admin.contents._modals.publish-confirm', [
            'id' => 'question-unpublish-modal',
            'title' => '問題を下書きに戻しますか？',
            'description' => '下書きに戻すと受講生からは非表示になります。',
            'action' => route('admin.questions.unpublish', $question),
            'buttonLabel' => '下書きに戻す',
            'buttonVariant' => 'secondary',
        ])
    @endif
@endsection

@push('scripts')
    @vite('resources/js/content-management/option-correct.js')
@endpush
