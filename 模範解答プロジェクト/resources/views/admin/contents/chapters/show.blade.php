@extends('layouts.app')

@section('title', $chapter->title . ' — Chapter 詳細')

@php
    use App\Enums\ContentStatus;
    $isDraft = $chapter->status === ContentStatus::Draft;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => $chapter->part->certification->name, 'href' => route('admin.certifications.show', $chapter->part->certification)],
        ['label' => '教材階層', 'href' => route('admin.certifications.parts.index', $chapter->part->certification)],
        ['label' => $chapter->part->title, 'href' => route('admin.parts.show', $chapter->part)],
        ['label' => $chapter->title],
    ]" />

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-ink-900">{{ $chapter->title }}</h1>
                @include('admin.contents._partials.status-pill', ['status' => $chapter->status])
            </div>
            <div class="text-xs text-ink-500 font-mono mt-1 tabular-nums">order #{{ $chapter->order }}</div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if ($isDraft)
                <x-button variant="primary" size="sm" data-modal-trigger="chapter-publish-modal">
                    <x-icon name="arrow-up-on-square" class="w-4 h-4" />
                    公開
                </x-button>
                <x-button variant="danger" size="sm" data-modal-trigger="chapter-delete-modal">
                    <x-icon name="trash" class="w-4 h-4" />
                    削除
                </x-button>
            @else
                <x-button variant="outline" size="sm" data-modal-trigger="chapter-unpublish-modal">
                    <x-icon name="arrow-uturn-left" class="w-4 h-4" />
                    下書きに戻す
                </x-button>
            @endif
        </div>
    </div>

    <x-card class="mt-6" padding="md">
        <h2 class="text-sm font-semibold text-ink-700 uppercase tracking-wide">Chapter 情報の編集</h2>
        <form method="POST" action="{{ route('admin.chapters.update', $chapter) }}" class="mt-4 space-y-4">
            @csrf
            @method('PATCH')
            <x-form.input
                name="title"
                label="タイトル"
                :value="old('title', $chapter->title)"
                :error="$errors->first('title')"
                :required="true"
                maxlength="200"
            />
            <x-form.textarea
                name="description"
                label="説明"
                :rows="3"
                :value="old('description', $chapter->description)"
                :error="$errors->first('description')"
                :maxlength="1000"
            />
            <div class="flex justify-end">
                <x-button type="submit" variant="primary">保存</x-button>
            </div>
        </form>
    </x-card>

    <div class="mt-6 flex items-center justify-between gap-4">
        <h2 class="text-lg font-semibold text-ink-900">Section 一覧</h2>
        <x-button variant="primary" size="sm" data-modal-trigger="section-create-modal">
            <x-icon name="plus" class="w-4 h-4" />
            新規 Section
        </x-button>
    </div>

    @if ($chapter->sections->isEmpty())
        <div class="mt-4">
            <x-card padding="none">
                <x-empty-state
                    icon="document-text"
                    title="まだ Section がありません"
                    description="「新規 Section」から作成してください。"
                />
            </x-card>
        </div>
    @else
        <div class="mt-4 space-y-3" id="sections-list" data-reorder-endpoint="{{ route('admin.chapters.sections.reorder', $chapter) }}">
            @foreach ($chapter->sections as $section)
                <x-card padding="md" data-reorder-id="{{ $section->id }}">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="text-xs font-mono text-ink-500 tabular-nums">#{{ $section->order }}</span>
                                <a href="{{ route('admin.sections.show', $section) }}"
                                   class="text-base font-semibold text-ink-900 hover:text-primary-700 transition-colors">
                                    {{ $section->title }}
                                </a>
                                @include('admin.contents._partials.status-pill', ['status' => $section->status])
                            </div>
                            @if ($section->description)
                                <p class="text-xs text-ink-500 mt-1 line-clamp-1">{{ $section->description }}</p>
                            @endif
                        </div>
                        <x-link-button href="{{ route('admin.sections.show', $section) }}" variant="ghost" size="sm">
                            <x-icon name="arrow-right" class="w-4 h-4" />
                            開く
                        </x-link-button>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-modal id="section-create-modal" title="Section を新規作成" size="lg">
        <x-slot:body>
            <form id="section-create-form" method="POST" action="{{ route('admin.chapters.sections.store', $chapter) }}" class="space-y-4">
                @csrf
                <x-form.input
                    name="title"
                    label="タイトル"
                    :value="old('title')"
                    :error="$errors->first('title')"
                    :required="true"
                    maxlength="200"
                />
                <x-form.textarea
                    name="description"
                    label="説明"
                    :rows="2"
                    :value="old('description')"
                    :error="$errors->first('description')"
                    :maxlength="1000"
                />
                <x-form.textarea
                    name="body"
                    label="本文 (Markdown)"
                    :rows="8"
                    :value="old('body')"
                    :error="$errors->first('body')"
                    :required="true"
                    :maxlength="50000"
                    placeholder="## はじめに&#10;&#10;Markdown 記法で本文を記述してください。"
                />
            </form>
        </x-slot:body>
        <x-slot:footer>
            <x-button variant="ghost" data-modal-close="section-create-modal">キャンセル</x-button>
            <x-button type="submit" form="section-create-form" variant="primary">作成</x-button>
        </x-slot:footer>
    </x-modal>

    @if ($isDraft)
        @include('admin.contents._modals.publish-confirm', [
            'id' => 'chapter-publish-modal',
            'title' => 'Chapter を公開しますか？',
            'description' => '公開すると配下の Section も受講生の閲覧対象になります（各子要素も公開状態である必要があります）。',
            'action' => route('admin.chapters.publish', $chapter),
        ])
        @include('admin.contents._modals.delete-confirm', [
            'id' => 'chapter-delete-modal',
            'title' => 'Chapter を削除しますか？',
            'description' => 'Chapter を SoftDelete します。配下の Section も連鎖的に非表示になります。',
            'action' => route('admin.chapters.destroy', $chapter),
        ])
    @else
        @include('admin.contents._modals.publish-confirm', [
            'id' => 'chapter-unpublish-modal',
            'title' => 'Chapter を下書きに戻しますか？',
            'description' => '下書きに戻すと受講生からは非表示になります。',
            'action' => route('admin.chapters.unpublish', $chapter),
            'buttonLabel' => '下書きに戻す',
            'buttonVariant' => 'secondary',
        ])
    @endif
@endsection
