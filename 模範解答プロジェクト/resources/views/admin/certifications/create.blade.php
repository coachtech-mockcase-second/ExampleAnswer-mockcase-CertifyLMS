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
        <form method="POST" action="{{ route('admin.certifications.store') }}" class="space-y-5">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <x-form.input
                    name="code"
                    label="資格コード"
                    :value="old('code')"
                    :error="$errors->first('code')"
                    placeholder="CERT-XXXX"
                    hint="重複不可。後から変更可能"
                    :required="true"
                    maxlength="50"
                />

                <x-form.input
                    name="slug"
                    label="スラッグ"
                    :value="old('slug')"
                    :error="$errors->first('slug')"
                    placeholder="basic-information"
                    hint="URL に使用されます"
                    :required="true"
                    maxlength="120"
                />

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

                <x-form.input
                    name="passing_score"
                    label="合格点（%）"
                    type="number"
                    :value="old('passing_score', 60)"
                    :error="$errors->first('passing_score')"
                    hint="1 〜 100 の整数"
                    :required="true"
                />

                <x-form.input
                    name="total_questions"
                    label="総問題数"
                    type="number"
                    :value="old('total_questions', 80)"
                    :error="$errors->first('total_questions')"
                    :required="true"
                />

                <x-form.input
                    name="exam_duration_minutes"
                    label="試験時間（分）"
                    type="number"
                    :value="old('exam_duration_minutes', 150)"
                    :error="$errors->first('exam_duration_minutes')"
                    :required="true"
                />
            </div>

            <x-form.textarea
                name="description"
                label="説明"
                :value="old('description')"
                :error="$errors->first('description')"
                :rows="4"
                :maxlength="2000"
                hint="任意、最大 2000 文字"
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
