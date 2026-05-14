@extends('layouts.app')

@section('title', '資格マスタの編集 — ' . $certification->name)

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
        ['label' => $certification->name, 'href' => route('admin.certifications.show', $certification)],
        ['label' => '編集'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">資格マスタの編集</h1>
        <p class="text-sm text-ink-500 mt-1">公開状態の変更は本画面では行えません。</p>
    </div>

    <x-card class="mt-6" padding="lg" shadow="sm">
        <form method="POST" action="{{ route('admin.certifications.update', $certification) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="grid gap-5 md:grid-cols-2">
                <x-form.input
                    name="code"
                    label="資格コード"
                    :value="old('code', $certification->code)"
                    :error="$errors->first('code')"
                    :required="true"
                    maxlength="50"
                />

                <x-form.input
                    name="slug"
                    label="スラッグ"
                    :value="old('slug', $certification->slug)"
                    :error="$errors->first('slug')"
                    :required="true"
                    maxlength="120"
                />

                <x-form.input
                    name="name"
                    label="資格名"
                    :value="old('name', $certification->name)"
                    :error="$errors->first('name')"
                    :required="true"
                    maxlength="100"
                />

                <x-form.select
                    name="category_id"
                    label="カテゴリ"
                    :options="$categoryOptions"
                    :value="old('category_id', $certification->category_id)"
                    :error="$errors->first('category_id')"
                    :required="true"
                />

                <x-form.select
                    name="difficulty"
                    label="難易度"
                    :options="$difficultyOptions"
                    :value="old('difficulty', $certification->difficulty->value)"
                    :error="$errors->first('difficulty')"
                    :required="true"
                />

                <x-form.input
                    name="passing_score"
                    label="合格点（%）"
                    type="number"
                    :value="old('passing_score', $certification->passing_score)"
                    :error="$errors->first('passing_score')"
                    :required="true"
                />

                <x-form.input
                    name="total_questions"
                    label="総問題数"
                    type="number"
                    :value="old('total_questions', $certification->total_questions)"
                    :error="$errors->first('total_questions')"
                    :required="true"
                />

                <x-form.input
                    name="exam_duration_minutes"
                    label="試験時間（分）"
                    type="number"
                    :value="old('exam_duration_minutes', $certification->exam_duration_minutes)"
                    :error="$errors->first('exam_duration_minutes')"
                    :required="true"
                />
            </div>

            <x-form.textarea
                name="description"
                label="説明"
                :value="old('description', $certification->description)"
                :error="$errors->first('description')"
                :rows="4"
                :maxlength="2000"
            />

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-link-button href="{{ route('admin.certifications.show', $certification) }}" variant="ghost">キャンセル</x-link-button>
                <x-button type="submit" variant="primary">
                    <x-icon name="check" class="w-4 h-4" />
                    更新する
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
