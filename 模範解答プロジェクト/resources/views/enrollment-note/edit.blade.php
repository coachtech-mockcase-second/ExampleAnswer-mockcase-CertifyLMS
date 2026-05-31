{{--
    コーチメモの編集ページ。
    構成: パンくず → 見出し → 編集フォーム(本文 + 保存・キャンセル)。
--}}
@extends('layouts.app')

@section('title', 'メモを編集')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '受講生詳細', 'href' => route('enrollments.show', $note->enrollment_id)],
        ['label' => 'メモを編集'],
    ]" />

    <h1 class="text-2xl font-bold mt-4">コーチメモを編集</h1>

    <x-card class="mt-6" padding="lg" shadow="sm">
        <form novalidate method="POST" action="{{ route('enrollment-notes.update', $note) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <x-form.textarea
                name="body"
                label="メモ本文"
                :rows="6"
                :value="old('body', $note->body)"
                :error="$errors->first('body')"
                :maxlength="2000"
                :required="true"
            />

            <div class="flex items-center gap-2 pt-2">
                <x-button type="submit" variant="primary">
                    保存
                </x-button>
                <x-link-button href="{{ route('enrollments.show', $note->enrollment_id) }}" variant="ghost">
                    キャンセル
                </x-link-button>
            </div>
        </form>
    </x-card>
@endsection
