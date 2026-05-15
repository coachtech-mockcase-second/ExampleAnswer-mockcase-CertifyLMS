@extends('layouts.app')

@section('title', '問題を新規作成 — ' . $certification->name)

@php
    use App\Enums\QuestionDifficulty;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => $certification->name, 'href' => route('admin.certifications.show', $certification)],
        ['label' => '問題管理', 'href' => route('admin.certifications.questions.index', $certification)],
        ['label' => '新規作成'],
    ]" />

    <h1 class="mt-4 text-2xl font-bold text-ink-900">問題を新規作成</h1>

    <form method="POST" action="{{ route('admin.certifications.questions.store', $certification) }}" class="mt-6 space-y-6">
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
                <div class="grid gap-4 sm:grid-cols-2">
                    @include('admin.contents.questions._partials.category-select', [
                        'categories' => $categories,
                        'selected' => old('category_id'),
                    ])
                    <x-form.select
                        name="difficulty"
                        label="難易度"
                        :options="collect(QuestionDifficulty::cases())->mapWithKeys(fn ($d) => [$d->value => $d->label()])->toArray()"
                        :value="old('difficulty', 'medium')"
                        :error="$errors->first('difficulty')"
                        :required="true"
                    />
                </div>
                <x-form.input
                    name="section_id"
                    label="紐づき Section ID (任意、空欄なら mock-exam 専用)"
                    :value="old('section_id')"
                    :error="$errors->first('section_id')"
                    hint="Section が指定されない場合は mock-exam 用問題として扱われます。"
                />
            </div>
        </x-card>

        <x-card padding="md">
            @include('admin.contents.questions._partials.option-fieldset', [
                'options' => old('options', []),
            ])
        </x-card>

        <div class="flex justify-end gap-2">
            <x-link-button href="{{ route('admin.certifications.questions.index', $certification) }}" variant="ghost">キャンセル</x-link-button>
            <x-button type="submit" variant="primary">下書きとして保存</x-button>
        </div>
    </form>
@endsection

@push('scripts')
    @vite('resources/js/content-management/option-correct.js')
@endpush
