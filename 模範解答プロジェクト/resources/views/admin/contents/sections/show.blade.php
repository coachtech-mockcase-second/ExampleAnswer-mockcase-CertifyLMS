@extends('layouts.app')

@section('title', $section->title . ' — Section 詳細')

@php
    use App\Enums\ContentStatus;
    $isDraft = $section->status === ContentStatus::Draft;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => $section->chapter->part->certification->name, 'href' => route('admin.certifications.show', $section->chapter->part->certification)],
        ['label' => '教材階層', 'href' => route('admin.certifications.parts.index', $section->chapter->part->certification)],
        ['label' => $section->chapter->part->title, 'href' => route('admin.parts.show', $section->chapter->part)],
        ['label' => $section->chapter->title, 'href' => route('admin.chapters.show', $section->chapter)],
        ['label' => $section->title],
    ]" />

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-ink-900">{{ $section->title }}</h1>
                @include('admin.contents._partials.status-pill', ['status' => $section->status])
            </div>
            <div class="text-xs text-ink-500 font-mono mt-1 tabular-nums">order #{{ $section->order }}</div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if ($isDraft)
                <x-button variant="primary" size="sm" data-modal-trigger="section-publish-modal">
                    <x-icon name="arrow-up-on-square" class="w-4 h-4" />
                    公開
                </x-button>
                <x-button variant="danger" size="sm" data-modal-trigger="section-delete-modal">
                    <x-icon name="trash" class="w-4 h-4" />
                    削除
                </x-button>
            @else
                <x-button variant="outline" size="sm" data-modal-trigger="section-unpublish-modal">
                    <x-icon name="arrow-uturn-left" class="w-4 h-4" />
                    下書きに戻す
                </x-button>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.sections.update', $section) }}" class="mt-6 space-y-6">
        @csrf
        @method('PATCH')

        <x-card padding="md">
            <h2 class="text-sm font-semibold text-ink-700 uppercase tracking-wide">基本情報</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-form.input
                    name="title"
                    label="タイトル"
                    :value="old('title', $section->title)"
                    :error="$errors->first('title')"
                    :required="true"
                    maxlength="200"
                />
                <x-form.input
                    name="description"
                    label="説明"
                    :value="old('description', $section->description)"
                    :error="$errors->first('description')"
                    maxlength="1000"
                />
            </div>
        </x-card>

        <x-card padding="md">
            @include('admin.contents.sections._partials.markdown-editor', [
                'section' => $section,
                'body' => old('body', $section->body),
            ])
            @if ($errors->has('body'))
                <p class="mt-2 text-xs text-danger-600">{{ $errors->first('body') }}</p>
            @endif
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button type="submit" variant="primary">保存</x-button>
        </div>
    </form>

    <x-card class="mt-6" padding="md">
        @include('admin.contents.sections._partials.image-uploader', ['section' => $section])
        <div class="mt-6">
            @include('admin.contents.sections._partials.image-list', ['section' => $section])
        </div>
    </x-card>

    @if ($isDraft)
        @include('admin.contents._modals.publish-confirm', [
            'id' => 'section-publish-modal',
            'title' => 'Section を公開しますか？',
            'description' => '公開すると受講生の教材閲覧画面に表示されます（親 Chapter / Part が公開済みの場合のみ受講生から見えます）。',
            'action' => route('admin.sections.publish', $section),
        ])
        @include('admin.contents._modals.delete-confirm', [
            'id' => 'section-delete-modal',
            'title' => 'Section を削除しますか？',
            'description' => 'Section を SoftDelete します。',
            'action' => route('admin.sections.destroy', $section),
        ])
    @else
        @include('admin.contents._modals.publish-confirm', [
            'id' => 'section-unpublish-modal',
            'title' => 'Section を下書きに戻しますか？',
            'description' => '下書きに戻すと受講生からは非表示になります。',
            'action' => route('admin.sections.unpublish', $section),
            'buttonLabel' => '下書きに戻す',
            'buttonVariant' => 'secondary',
        ])
    @endif
@endsection

@push('scripts')
    @vite('resources/js/content-management/section-editor.js')
    @vite('resources/js/content-management/image-uploader.js')
@endpush
