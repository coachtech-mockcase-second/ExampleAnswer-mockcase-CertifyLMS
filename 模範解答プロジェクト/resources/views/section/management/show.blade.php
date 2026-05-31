{{--
    Section 詳細・編集画面。Section の基本情報 / 本文（Markdown）/ 画像を編集する。
    構成: パンくず → ヘッダ（タイトル + 状態バッジ + 演習問題リンク + 公開/削除 or 下書きに戻すボタン）→ 編集フォーム（基本情報カード + Markdown エディタカード）→ 画像カード（アップローダ + 画像一覧）→ 状態遷移確認モーダル群
    フロント観点: Markdown エディタは入力に応じてプレビューを更新、画像はアップロード後に本文へ Markdown 自動挿入（いずれも素の JS）。公開・削除・下書き化はモーダル経由。下書き/公開でヘッダのボタンを出し分け。
--}}
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
                <x-content-management.status-pill :status="$section->status" />
            </div>
            <div class="text-xs text-ink-500 font-mono mt-1 tabular-nums">order #{{ $section->order }}</div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <x-link-button href="{{ route('admin.sections.questions.index', $section) }}" variant="outline" size="sm">
                <x-icon name="question-mark-circle" class="w-4 h-4" />
                演習問題
            </x-link-button>
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

    <form novalidate method="POST" action="{{ route('admin.sections.update', $section) }}" class="mt-6 space-y-6">
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
            @include('section.management._partials.markdown-editor', [
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
        @include('section.management._partials.image-uploader', ['section' => $section])
        <div class="mt-6">
            @include('section.management._partials.image-list', ['section' => $section])
        </div>
    </x-card>

    @if ($isDraft)
        <x-content-management.publish-confirm-modal
            id="section-publish-modal"
            title="Section を公開しますか？"
            description="公開すると受講生の教材閲覧画面に表示されます（親 Chapter / Part が公開済みの場合のみ受講生から見えます）。"
            :action="route('admin.sections.publish', $section)"
        />
        <x-content-management.delete-confirm-modal
            id="section-delete-modal"
            title="Section を削除しますか？"
            description="Section を削除します。"
            :action="route('admin.sections.destroy', $section)"
        />
    @else
        <x-content-management.publish-confirm-modal
            id="section-unpublish-modal"
            title="Section を下書きに戻しますか？"
            description="下書きに戻すと受講生からは非表示になります。"
            :action="route('admin.sections.unpublish', $section)"
            button-label="下書きに戻す"
            button-variant="secondary"
        />
    @endif
@endsection

@push('scripts')
    @vite('resources/js/content-management/section-editor.js')
    @vite('resources/js/content-management/image-uploader.js')
@endpush
