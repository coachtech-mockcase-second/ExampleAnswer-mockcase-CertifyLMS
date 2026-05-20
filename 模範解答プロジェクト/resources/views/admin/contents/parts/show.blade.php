@extends('layouts.app')

@section('title', $part->title . ' — Part 詳細')

@php
    use App\Enums\ContentStatus;
    $isDraft = $part->status === ContentStatus::Draft;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => $part->certification->name, 'href' => route('admin.certifications.show', $part->certification)],
        ['label' => '教材階層', 'href' => route('admin.certifications.parts.index', $part->certification)],
        ['label' => $part->title],
    ]" />

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-ink-900">{{ $part->title }}</h1>
                @include('admin.contents._partials.status-pill', ['status' => $part->status])
            </div>
            <div class="text-xs text-ink-500 font-mono mt-1 tabular-nums">order #{{ $part->order }}</div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if ($isDraft)
                <x-button variant="primary" size="sm" data-modal-trigger="part-publish-modal">
                    <x-icon name="arrow-up-on-square" class="w-4 h-4" />
                    公開
                </x-button>
                <x-button variant="danger" size="sm" data-modal-trigger="part-delete-modal">
                    <x-icon name="trash" class="w-4 h-4" />
                    削除
                </x-button>
            @else
                <x-button variant="outline" size="sm" data-modal-trigger="part-unpublish-modal">
                    <x-icon name="arrow-uturn-left" class="w-4 h-4" />
                    下書きに戻す
                </x-button>
            @endif
        </div>
    </div>

    <x-card class="mt-6" padding="md">
        <h2 class="text-sm font-semibold text-ink-700 uppercase tracking-wide">Part 情報の編集</h2>
        <form method="POST" action="{{ route('admin.parts.update', $part) }}" class="mt-4 space-y-4">
            @csrf
            @method('PATCH')
            <x-form.input
                name="title"
                label="タイトル"
                :value="old('title', $part->title)"
                :error="$errors->first('title')"
                :required="true"
                maxlength="200"
            />
            <x-form.textarea
                name="description"
                label="説明"
                :rows="3"
                :value="old('description', $part->description)"
                :error="$errors->first('description')"
                :maxlength="1000"
            />
            <div class="flex justify-end">
                <x-button type="submit" variant="primary">保存</x-button>
            </div>
        </form>
    </x-card>

    <div class="mt-6 flex items-center justify-between gap-4">
        <h2 class="text-lg font-semibold text-ink-900">Chapter 一覧</h2>
        <x-button variant="primary" size="sm" data-modal-trigger="chapter-create-modal">
            <x-icon name="plus" class="w-4 h-4" />
            新規 Chapter
        </x-button>
    </div>

    @if ($part->chapters->isEmpty())
        <div class="mt-4">
            <x-card padding="none">
                <x-empty-state
                    icon="document-text"
                    title="まだ Chapter がありません"
                    description="「新規 Chapter」から作成してください。"
                />
            </x-card>
        </div>
    @else
        <div class="mt-4 space-y-3" id="chapters-list" data-reorder-endpoint="{{ route('admin.parts.chapters.reorder', $part) }}">
            @foreach ($part->chapters as $chapter)
                <x-card padding="md" data-reorder-id="{{ $chapter->id }}">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="text-xs font-mono text-ink-500 tabular-nums">#{{ $chapter->order }}</span>
                                <a href="{{ route('admin.chapters.show', $chapter) }}"
                                   class="text-base font-semibold text-ink-900 hover:text-primary-700 transition-colors">
                                    {{ $chapter->title }}
                                </a>
                                @include('admin.contents._partials.status-pill', ['status' => $chapter->status])
                            </div>
                            <div class="text-xs text-ink-500 mt-1 tabular-nums">
                                Section {{ $chapter->sections_count ?? 0 }} 件
                            </div>
                        </div>
                        <x-link-button href="{{ route('admin.chapters.show', $chapter) }}" variant="ghost" size="sm">
                            <x-icon name="arrow-right" class="w-4 h-4" />
                            開く
                        </x-link-button>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-modal id="chapter-create-modal" title="Chapter を新規作成" size="md">
        <x-slot:body>
            <form id="chapter-create-form" method="POST" action="{{ route('admin.parts.chapters.store', $part) }}" class="space-y-4">
                @csrf
                <x-form.input
                    name="title"
                    label="タイトル"
                    :value="old('title')"
                    :error="$errors->first('title')"
                    :required="true"
                    maxlength="200"
                    placeholder="例: 第1章 進数と論理演算"
                />
                <x-form.textarea
                    name="description"
                    label="説明"
                    :rows="3"
                    :value="old('description')"
                    :error="$errors->first('description')"
                    :maxlength="1000"
                />
            </form>
        </x-slot:body>
        <x-slot:footer>
            <x-button variant="ghost" data-modal-close="chapter-create-modal">キャンセル</x-button>
            <x-button type="submit" form="chapter-create-form" variant="primary">作成</x-button>
        </x-slot:footer>
    </x-modal>

    @if ($isDraft)
        @include('admin.contents._modals.publish-confirm', [
            'id' => 'part-publish-modal',
            'title' => 'Part を公開しますか？',
            'description' => '公開すると配下の Chapter / Section も受講生の閲覧対象になります（各子要素も公開状態である必要があります）。',
            'action' => route('admin.parts.publish', $part),
            'buttonLabel' => '公開する',
            'buttonVariant' => 'primary',
        ])
        @include('admin.contents._modals.delete-confirm', [
            'id' => 'part-delete-modal',
            'title' => 'Part を削除しますか？',
            'description' => 'Part を SoftDelete します。配下の Chapter / Section も連鎖的に非表示になります。',
            'action' => route('admin.parts.destroy', $part),
            'buttonLabel' => '削除する',
        ])
    @else
        @include('admin.contents._modals.publish-confirm', [
            'id' => 'part-unpublish-modal',
            'title' => 'Part を下書きに戻しますか？',
            'description' => '下書きに戻すと受講生からは非表示になります。',
            'action' => route('admin.parts.unpublish', $part),
            'buttonLabel' => '下書きに戻す',
            'buttonVariant' => 'secondary',
        ])
    @endif
@endsection
