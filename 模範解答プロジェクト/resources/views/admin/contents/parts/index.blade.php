@extends('layouts.app')

@section('title', '教材階層 — ' . $certification->name)

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => $certification->name, 'href' => route('admin.certifications.show', $certification)],
        ['label' => '教材階層'],
    ]" />

    <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">教材階層</h1>
            <p class="text-sm text-ink-500 mt-1">
                <span class="font-semibold text-ink-700">{{ $certification->name }}</span> の Part / Chapter / Section を管理します。
            </p>
        </div>
        <x-button variant="primary" data-modal-trigger="part-create-modal">
            <x-icon name="plus" class="w-4 h-4" />
            新規 Part
        </x-button>
    </div>

    @if ($parts->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="book-open"
                    title="まだ Part がありません"
                    description="「新規 Part」ボタンから最初の Part を追加してください。"
                />
            </x-card>
        </div>
    @else
        <div class="mt-6 space-y-4" id="parts-list" data-reorder-endpoint="{{ route('admin.certifications.parts.reorder', $certification) }}">
            @foreach ($parts as $part)
                <x-card padding="md" data-reorder-id="{{ $part->id }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="text-xs font-mono text-ink-500 tabular-nums">#{{ $part->order }}</span>
                                <a href="{{ route('admin.parts.show', $part) }}"
                                   class="text-lg font-semibold text-ink-900 hover:text-primary-700 transition-colors">
                                    {{ $part->title }}
                                </a>
                                @include('admin.contents._partials.status-pill', ['status' => $part->status])
                            </div>
                            @if ($part->description)
                                <p class="text-sm text-ink-500 mt-1 line-clamp-2">{{ $part->description }}</p>
                            @endif
                            <div class="text-xs text-ink-500 mt-2 tabular-nums">
                                Chapter {{ $part->chapters->count() }} 件 · Section {{ $part->chapters->sum('sections_count') }} 件
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <x-link-button href="{{ route('admin.parts.show', $part) }}" variant="ghost" size="sm">
                                <x-icon name="arrow-right" class="w-4 h-4" />
                                開く
                            </x-link-button>
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-modal id="part-create-modal" title="Part を新規作成" size="md">
        <x-slot:body>
            <form id="part-create-form" method="POST" action="{{ route('admin.certifications.parts.store', $certification) }}" class="space-y-4">
                @csrf
                <x-form.input
                    name="title"
                    label="タイトル"
                    :value="old('title')"
                    :error="$errors->first('title')"
                    :required="true"
                    placeholder="例: 第1部 基礎理論"
                    maxlength="200"
                />
                <x-form.textarea
                    name="description"
                    label="説明"
                    :rows="3"
                    :value="old('description')"
                    :error="$errors->first('description')"
                    :maxlength="1000"
                    placeholder="任意"
                />
            </form>
        </x-slot:body>
        <x-slot:footer>
            <x-button variant="ghost" data-modal-close="part-create-modal">キャンセル</x-button>
            <x-button type="submit" form="part-create-form" variant="primary">作成</x-button>
        </x-slot:footer>
    </x-modal>
@endsection
