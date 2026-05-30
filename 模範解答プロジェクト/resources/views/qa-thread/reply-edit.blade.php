@extends('layouts.app')

@section('title', '回答を編集')

@section('content')
    {{--
        回答編集フォーム。本文のみ更新する専用ページ。
        構成: パンくず → カード（見出し + 本文 textarea + 更新/キャンセル）。
        フロント観点: JS なし（フォーム POST + @method('PATCH') で更新、編集はインラインでなく専用ページ遷移）。
    --}}
    {{-- パンくず --}}
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '質問掲示板', 'href' => route('qa-board.index')],
        ['label' => $thread->title, 'href' => route('qa-board.show', $thread)],
        ['label' => '回答を編集'],
    ]" />

    {{-- 回答編集フォームのカード --}}
    <x-card class="mt-4" padding="lg">
        <h1 class="text-xl font-bold text-ink-900">回答を編集</h1>
        <p class="mt-1 text-sm text-ink-500">{{ $thread->title }} への回答を編集します。</p>

        {{-- 更新フォーム。@csrf は全フォーム必須、@method('PATCH') で更新リクエストに偽装する --}}
        <form
            method="POST"
            action="{{ route('qa-board.replies.update', ['thread' => $thread->id, 'reply' => $reply->id]) }}"
            class="mt-4 flex flex-col gap-4"
        >
            @csrf
            @method('PATCH')

            {{-- 本文入力欄。old() で再入力値を優先し、初回は既存の回答本文を表示 --}}
            <x-form.textarea
                name="body"
                label="回答本文"
                :rows="6"
                :value="old('body', $reply->body)"
                :error="$errors->first('body')"
                :maxlength="5000"
                :required="true"
            />

            {{-- 送信 / キャンセル --}}
            <div class="flex items-center justify-end gap-3">
                <x-link-button href="{{ route('qa-board.show', $thread) }}" variant="ghost">キャンセル</x-link-button>
                <x-button type="submit" variant="primary">
                    <x-icon name="check" class="w-4 h-4" />
                    更新する
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
