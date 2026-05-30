{{--
    個人目標の編集ページ。
    構成: パンくず → 見出し → 編集フォーム(目標 / 期日 / 詳細 + 保存・キャンセル)。
--}}
@extends('layouts.app')

@section('title', '目標を編集')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '受講中資格', 'href' => route('enrollments.show', $goal->enrollment_id)],
        ['label' => '目標を編集'],
    ]" />

    <h1 class="text-2xl font-bold mt-4">目標を編集</h1>

    <x-card class="mt-6" padding="lg" shadow="sm">
        <form method="POST" action="{{ route('enrollment-goals.update', $goal) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <x-form.input
                name="title"
                label="目標"
                :value="old('title', $goal->title)"
                :error="$errors->first('title')"
                placeholder="例: 過去問 5 年分を解き終える"
                maxlength="100"
                :required="true"
            />

            <x-form.input
                name="target_date"
                label="目標期日"
                type="date"
                :value="old('target_date', $goal->target_date?->format('Y-m-d'))"
                :error="$errors->first('target_date')"
            />

            <x-form.textarea
                name="description"
                label="詳細(任意)"
                :rows="4"
                :value="old('description', $goal->description)"
                :error="$errors->first('description')"
                :maxlength="1000"
            />

            <div class="flex items-center gap-2 pt-2">
                <x-button type="submit" variant="primary">
                    保存
                </x-button>
                <x-link-button href="{{ route('enrollments.show', $goal->enrollment_id) }}" variant="ghost">
                    キャンセル
                </x-link-button>
            </div>
        </form>
    </x-card>
@endsection
