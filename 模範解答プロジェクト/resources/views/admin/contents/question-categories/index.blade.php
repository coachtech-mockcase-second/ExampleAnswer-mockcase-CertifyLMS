@extends('layouts.app')

@section('title', '問題カテゴリマスタ — ' . $certification->name)

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => $certification->name, 'href' => route('admin.certifications.show', $certification)],
        ['label' => '問題管理', 'href' => route('admin.certifications.questions.index', $certification)],
        ['label' => 'カテゴリマスタ'],
    ]" />

    <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">問題カテゴリマスタ</h1>
            <p class="text-sm text-ink-500 mt-1">
                資格内のカテゴリを管理します。Question 作成時の select 候補になります。
            </p>
        </div>
        <x-button variant="primary" data-modal-trigger="question-category-create-modal">
            <x-icon name="plus" class="w-4 h-4" />
            新規カテゴリ
        </x-button>
    </div>

    @if ($categories->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="tag"
                    title="まだカテゴリがありません"
                    description="「新規カテゴリ」から追加してください。問題作成時に select で選べるようになります。"
                />
            </x-card>
        </div>
    @else
        <div class="mt-6">
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading class="w-20">順序</x-table.heading>
                        <x-table.heading>名称 / スラッグ</x-table.heading>
                        <x-table.heading>説明</x-table.heading>
                        <x-table.heading class="text-right">紐付き問題</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>
                @foreach ($categories as $cat)
                    <x-table.row>
                        <x-table.cell>
                            <span class="text-xs font-mono text-ink-500 tabular-nums">#{{ $cat->sort_order }}</span>
                        </x-table.cell>
                        <x-table.cell>
                            <div class="text-sm font-semibold text-ink-900">{{ $cat->name }}</div>
                            <div class="text-xs text-ink-500 font-mono">{{ $cat->slug }}</div>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-xs text-ink-500 line-clamp-2 max-w-md">{{ $cat->description ?? '—' }}</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <span class="text-xs font-mono text-ink-700 tabular-nums">{{ $cat->questions_count ?? 0 }} 件</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <x-button variant="ghost" size="sm" data-modal-trigger="question-category-edit-modal-{{ $cat->id }}">
                                <x-icon name="pencil" class="w-4 h-4" />
                                編集
                            </x-button>
                            <x-button variant="ghost" size="sm" data-modal-trigger="question-category-delete-modal-{{ $cat->id }}">
                                <x-icon name="trash" class="w-4 h-4" />
                                削除
                            </x-button>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        @foreach ($categories as $cat)
            @include('admin.contents.question-categories._modals.form', [
                'id' => 'question-category-edit-modal-' . $cat->id,
                'title' => 'カテゴリを編集',
                'action' => route('admin.question-categories.update', $cat),
                'method' => 'PATCH',
                'category' => $cat,
            ])
            @include('admin.contents.question-categories._modals.delete-confirm', [
                'id' => 'question-category-delete-modal-' . $cat->id,
                'category' => $cat,
            ])
        @endforeach
    @endif

    @include('admin.contents.question-categories._modals.form', [
        'id' => 'question-category-create-modal',
        'title' => 'カテゴリを追加',
        'action' => route('admin.certifications.question-categories.store', $certification),
        'method' => 'POST',
        'category' => null,
    ])
@endsection
