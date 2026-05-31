@extends('layouts.app')

@section('title', '質問を編集')

@section('content')
    {{-- 質問編集フォーム。資格は変更不可（バッジ表示のみ）、タイトル + 本文を更新。@method('PATCH') で更新リクエストにする --}}
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '質問掲示板', 'href' => route('qa-board.index')],
        ['label' => $thread->title, 'href' => route('qa-board.show', $thread)],
        ['label' => '編集'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">質問を編集</h1>
        <p class="text-sm text-ink-500 mt-1">
            タイトルと本文を更新できます。資格の差し替えは新しい質問として投稿してください。
        </p>
    </div>

    <x-card class="mt-6" padding="md" shadow="sm">
        <form novalidate method="POST" action="{{ route('qa-board.update', $thread) }}" class="flex flex-col gap-4">
            @csrf
            @method('PATCH')

            <div>
                <span class="text-xs font-medium text-ink-500">資格</span>
                <div class="mt-1 inline-flex">
                    <x-badge variant="gray">{{ $thread->certification?->name ?? '未設定' }}</x-badge>
                </div>
            </div>

            <x-form.input
                name="title"
                label="タイトル"
                :value="old('title', $thread->title)"
                :error="$errors->first('title')"
                :required="true"
                hint="200 文字以内"
                maxlength="200"
            />

            <x-form.textarea
                name="body"
                label="本文"
                :rows="10"
                :value="old('body', $thread->body)"
                :error="$errors->first('body')"
                :maxlength="5000"
                :required="true"
            />

            <div class="flex items-center justify-end gap-3 pt-2">
                <x-link-button href="{{ route('qa-board.show', $thread) }}" variant="ghost">キャンセル</x-link-button>
                <x-button type="submit" variant="primary">
                    <x-icon name="check" class="w-4 h-4" />
                    更新する
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
