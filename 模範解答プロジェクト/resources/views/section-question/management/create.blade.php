{{--
    演習問題の新規作成画面。問題文・解説・出題分野・選択肢を 1 フォームで入力する。
    構成: パンくず → タイトル → 作成フォーム（基本情報カード[問題文 / 解説 / 分野セレクト partial] + 選択肢カード[option-fieldset partial]）→ キャンセル / 保存ボタン
    フロント観点: 選択肢の正答ラジオ選択は素の JS（option-correct.js）で hidden 値に同期。
--}}
@extends('layouts.app')

@section('title', '演習問題を新規作成 — ' . $section->title)

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => $section->chapter->part->certification->name, 'href' => route('admin.certifications.show', $section->chapter->part->certification)],
        ['label' => $section->title, 'href' => route('admin.sections.show', $section)],
        ['label' => '演習問題', 'href' => route('admin.sections.questions.index', $section)],
        ['label' => '新規作成'],
    ]" />

    <h1 class="mt-4 text-2xl font-bold text-ink-900">演習問題を新規作成</h1>

    <form method="POST" action="{{ route('admin.sections.questions.store', $section) }}" class="mt-6 space-y-6">
        @csrf

        <x-card padding="md">
            <h2 class="text-sm font-semibold text-ink-700 uppercase tracking-wide">基本情報</h2>
            <div class="mt-4 space-y-4">
                <x-form.textarea
                    name="body"
                    label="問題文"
                    :rows="4"
                    :value="old('body')"
                    :error="$errors->first('body')"
                    :required="true"
                    :maxlength="5000"
                />
                <x-form.textarea
                    name="explanation"
                    label="解説 (任意)"
                    :rows="3"
                    :value="old('explanation')"
                    :error="$errors->first('explanation')"
                    :maxlength="5000"
                />
                @include('section-question.management._partials.category-select', [
                    'categories' => $categories,
                    'selected' => old('category_id'),
                ])
            </div>
        </x-card>

        <x-card padding="md">
            @include('section-question.management._partials.option-fieldset', [
                'options' => old('options', []),
            ])
        </x-card>

        <div class="flex justify-end gap-2">
            <x-link-button href="{{ route('admin.sections.questions.index', $section) }}" variant="ghost">キャンセル</x-link-button>
            <x-button type="submit" variant="primary">下書きとして保存</x-button>
        </div>
    </form>
@endsection

@push('scripts')
    @vite('resources/js/content-management/option-correct.js')
@endpush
