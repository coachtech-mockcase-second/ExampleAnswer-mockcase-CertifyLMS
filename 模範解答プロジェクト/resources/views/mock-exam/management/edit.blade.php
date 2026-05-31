{{--
    模試マスタの編集フォーム画面（管理側）。資格は変更不可で表示のみ。
    構成: パンくず → 見出し + 説明 → カード内フォーム（資格は読み取り表示 / 模試名 / 説明 / 並び順 / 合格点 → 更新・キャンセル）
    フロント観点: JS なし（標準フォーム + @method('PUT') + リダイレクト）。各値は old() で現在値プリフィル。
--}}
@extends('layouts.app')

@section('title', $mockExam->title . ' — 編集')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試マスタ管理', 'href' => route('admin.mock-exams.index')],
        ['label' => $mockExam->title, 'href' => route('admin.mock-exams.show', $mockExam)],
        ['label' => '編集'],
    ]" />

    <h1 class="mt-4 text-2xl font-bold text-ink-900">模試マスタを編集</h1>
    <p class="mt-1 text-sm text-ink-500">資格の変更はできません。模試名・説明・並び順・合格点を更新できます。</p>

    <x-card class="mt-6 max-w-2xl" padding="md" shadow="sm">
        <form novalidate method="POST" action="{{ route('admin.mock-exams.update', $mockExam) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <p class="text-xs text-ink-500 mb-1">資格(変更不可)</p>
                <p class="text-sm font-semibold text-ink-900">{{ $mockExam->certification->name }}</p>
            </div>

            <x-form.input
                name="title"
                label="模試名"
                :value="old('title', $mockExam->title)"
                :error="$errors->first('title')"
                :required="true"
            />

            <x-form.textarea
                name="description"
                label="説明"
                :rows="3"
                :value="old('description', $mockExam->description)"
                :error="$errors->first('description')"
                :maxlength="2000"
            />

            <div class="grid grid-cols-2 gap-4">
                <x-form.input
                    name="order"
                    label="並び順"
                    type="number"
                    :value="old('order', $mockExam->order)"
                    :error="$errors->first('order')"
                    :required="true"
                />

                <x-form.input
                    name="passing_score"
                    label="合格点 (%)"
                    type="number"
                    :value="old('passing_score', $mockExam->passing_score)"
                    :error="$errors->first('passing_score')"
                    :required="true"
                />
            </div>

            <div class="flex items-center gap-2 pt-2">
                <x-button type="submit" variant="primary">
                    <x-icon name="check" class="w-4 h-4" />
                    更新する
                </x-button>
                <x-link-button href="{{ route('admin.mock-exams.show', $mockExam) }}" variant="ghost">キャンセル</x-link-button>
            </div>
        </form>
    </x-card>
@endsection
