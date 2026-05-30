{{--
    模試マスタの新規作成フォーム画面（管理側）。問題セットは作成後に別画面で追加。
    構成: パンくず → 見出し + 説明 → カード内フォーム（資格 select / 模試名 / 説明 textarea / 並び順 / 合格点 → 作成・キャンセル）
    フロント観点: JS なし（標準フォーム POST + リダイレクト）。エラーは old() 復元 + 各 input の :error 表示。
--}}
@extends('layouts.app')

@section('title', '模試マスタ — 新規作成')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試マスタ管理', 'href' => route('admin.mock-exams.index')],
        ['label' => '新規作成'],
    ]" />

    <h1 class="mt-4 text-2xl font-bold text-ink-900">模試マスタを新規作成</h1>
    <p class="mt-1 text-sm text-ink-500">
        資格と模試名、合格点を入力してください。問題セットは作成後に追加します。
    </p>

    <x-card class="mt-6 max-w-2xl" padding="md" shadow="sm">
        <form method="POST" action="{{ route('admin.mock-exams.store') }}" class="space-y-5">
            @csrf

            <x-form.select
                name="certification_id"
                label="資格"
                :options="$certifications->pluck('name', 'id')->all()"
                :value="old('certification_id')"
                :error="$errors->first('certification_id')"
                placeholder="選択してください"
                :required="true"
            />

            <x-form.input
                name="title"
                label="模試名"
                :value="old('title')"
                :error="$errors->first('title')"
                placeholder="例: 第 3 回 本番形式模擬試験"
                :required="true"
            />

            <x-form.textarea
                name="description"
                label="説明"
                :rows="3"
                :value="old('description')"
                :error="$errors->first('description')"
                :maxlength="2000"
                hint="受講生に表示される説明文(任意)"
            />

            <div class="grid grid-cols-2 gap-4">
                <x-form.input
                    name="order"
                    label="並び順"
                    type="number"
                    :value="old('order', 0)"
                    :error="$errors->first('order')"
                    :required="true"
                    hint="同一資格内での表示順(小さいほど上)"
                />

                <x-form.input
                    name="passing_score"
                    label="合格点 (%)"
                    type="number"
                    :value="old('passing_score', 60)"
                    :error="$errors->first('passing_score')"
                    :required="true"
                    hint="1〜100 の整数。受講生がこの百分率を超えると合格"
                />
            </div>

            <div class="flex items-center gap-2 pt-2">
                <x-button type="submit" variant="primary">
                    <x-icon name="check" class="w-4 h-4" />
                    作成する(下書きとして保存)
                </x-button>
                <x-link-button href="{{ route('admin.mock-exams.index') }}" variant="ghost">キャンセル</x-link-button>
            </div>
        </form>
    </x-card>
@endsection
