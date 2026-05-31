{{--
    資格マスタの新規作成画面。
    構成: パンくず → 見出し → 入力フォームカード(資格名 / カテゴリ / 難易度 / 説明 + キャンセル・保存)
    フロント観点: POST フォーム送信(JS なし)。冒頭の処理で難易度・カテゴリの select 選択肢を組み立て。
--}}
@extends('layouts.app')

@section('title', '資格マスタの新規作成')

@php
    use App\Enums\CertificationDifficulty;

    $difficultyOptions = collect(CertificationDifficulty::cases())
        ->mapWithKeys(fn ($d) => [$d->value => $d->label()])
        ->all();

    $categoryOptions = $categories
        ->mapWithKeys(fn ($c) => [$c->id => $c->name])
        ->all();
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => '新規作成'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">資格マスタの新規作成</h1>
        <p class="text-sm text-ink-500 mt-1">下書きとして保存されます。公開は作成後に行います。</p>
    </div>

    <x-card class="mt-6" padding="lg" shadow="sm">
        <form novalidate method="POST" action="{{ route('admin.certifications.store') }}" class="space-y-5">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <x-form.input
                    name="name"
                    label="資格名"
                    :value="old('name')"
                    :error="$errors->first('name')"
                    placeholder="基本情報技術者試験"
                    :required="true"
                    maxlength="100"
                />

                <x-form.select
                    name="category_id"
                    label="カテゴリ"
                    :options="$categoryOptions"
                    :value="old('category_id')"
                    :error="$errors->first('category_id')"
                    placeholder="選択してください"
                    :required="true"
                />

                <x-form.select
                    name="difficulty"
                    label="難易度"
                    :options="$difficultyOptions"
                    :value="old('difficulty')"
                    :error="$errors->first('difficulty')"
                    placeholder="選択してください"
                    :required="true"
                />
            </div>

            <x-form.textarea
                name="description"
                label="説明"
                :value="old('description')"
                :error="$errors->first('description')"
                :rows="4"
                :maxlength="1000"
                hint="任意、最大 1000 文字"
            />

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-link-button href="{{ route('admin.certifications.index') }}" variant="ghost">キャンセル</x-link-button>
                <x-button type="submit" variant="primary">
                    <x-icon name="check" class="w-4 h-4" />
                    下書きとして保存
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
