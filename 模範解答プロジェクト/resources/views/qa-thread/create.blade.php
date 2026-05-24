@extends('layouts.app')

@section('title', '質問を投稿')

@section('content')
    {{-- 質問投稿フォーム。資格セレクト + タイトル + 本文の入力欄で構成 --}}
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '質問掲示板', 'href' => route('qa-board.index')],
        ['label' => '質問を投稿'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">質問を投稿</h1>
        <p class="text-sm text-ink-500 mt-1">
            他の受講生にも公開されます。具体的に何が分からないか、何を試したかを書くと回答が得やすくなります。
        </p>
    </div>

    <x-card class="mt-6" padding="md" shadow="sm">
        <form method="POST" action="{{ route('qa-board.store') }}" class="flex flex-col gap-4">
            @csrf

            <x-form.select
                name="certification_id"
                label="資格"
                :options="$certifications->pluck('name', 'id')->toArray()"
                :value="old('certification_id')"
                :error="$errors->first('certification_id')"
                placeholder="公開中の資格から選択"
                :required="true"
                hint="受講していない資格でも質問できます。"
            />

            <x-form.input
                name="title"
                label="タイトル"
                :value="old('title')"
                :error="$errors->first('title')"
                placeholder="例: 2 分探索木の平均比較回数のオーダーがイメージできません"
                :required="true"
                hint="200 文字以内"
                maxlength="200"
            />

            <x-form.textarea
                name="body"
                label="本文"
                :rows="10"
                :value="old('body')"
                :error="$errors->first('body')"
                :maxlength="5000"
                :required="true"
                placeholder="教材のどの部分でつまずいたか / 自分で調べて分かったこと / それでも分からない点 を書くと回答しやすいです。"
            />

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-link-button href="{{ route('qa-board.index') }}" variant="ghost">キャンセル</x-link-button>
                <x-button type="submit" variant="primary">
                    <x-icon name="paper-airplane" class="w-4 h-4" />
                    投稿する
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
