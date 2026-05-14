@extends('layouts.app')

@section('title', '資格分類マスタ')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格分類マスタ'],
    ]" />

    <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">資格分類マスタ</h1>
            <p class="text-sm text-ink-500 mt-1">資格カタログのカテゴリ。<span class="font-semibold text-ink-700">{{ $categories->count() }} 件</span></p>
        </div>
        <x-button variant="primary" data-modal-trigger="category-create-modal">
            <x-icon name="plus" class="w-4 h-4" />
            分類を追加
        </x-button>
    </div>

    @if ($categories->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="tag"
                    title="分類がまだありません"
                    description="新しい分類を追加してください。"
                >
                    <x-slot:action>
                        <x-button variant="primary" data-modal-trigger="category-create-modal">
                            <x-icon name="plus" class="w-4 h-4" />
                            分類を追加
                        </x-button>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        </div>
    @else
        <div class="mt-6">
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>分類名</x-table.heading>
                        <x-table.heading>スラッグ</x-table.heading>
                        <x-table.heading>表示順</x-table.heading>
                        <x-table.heading class="text-right">資格数</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>

                @foreach ($categories as $category)
                    <x-table.row>
                        <x-table.cell>
                            <div class="text-sm font-semibold text-ink-900">{{ $category->name }}</div>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-xs font-mono text-ink-500">{{ $category->slug }}</span>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-xs font-mono text-ink-700 tabular-nums">{{ $category->sort_order }}</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <span class="text-xs text-ink-500 font-mono tabular-nums">{{ $category->certifications_count ?? 0 }} 件</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <div class="inline-flex gap-1">
                                <x-button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    :data-modal-trigger="'category-edit-modal-' . $category->id"
                                >
                                    <x-icon name="pencil" class="w-4 h-4" />
                                </x-button>
                                <form method="POST" action="{{ route('admin.certification-categories.destroy', $category) }}" onsubmit="return confirm('この分類を削除しますか？')">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" variant="ghost" size="sm">
                                        <x-icon name="trash" class="w-4 h-4" />
                                    </x-button>
                                </form>
                            </div>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>
    @endif

    @include('admin.certification-categories._modals.form', [
        'modalId' => 'category-create-modal',
        'title' => '分類を追加',
        'action' => route('admin.certification-categories.store'),
        'method' => 'POST',
        'category' => null,
    ])

    @foreach ($categories as $category)
        @include('admin.certification-categories._modals.form', [
            'modalId' => 'category-edit-modal-' . $category->id,
            'title' => '分類を編集',
            'action' => route('admin.certification-categories.update', $category),
            'method' => 'PUT',
            'category' => $category,
        ])
    @endforeach
@endsection
