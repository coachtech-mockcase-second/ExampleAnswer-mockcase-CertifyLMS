@extends('layouts.app')

@section('title', 'AI 相談')

@section('content')
    {{--
        AI 相談のフル画面（履歴が 0 件のときの初期表示）。
        構成: パンくず → 見出し → 空状態カード（相談を始めるボタン）→ 新規会話モーダル。
        フロント観点: ボタンは data-modal-trigger でモーダルを開く（素の JS）。
    --}}
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'AI 相談'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-display font-bold tracking-tight text-ink-900">AI 相談</h1>
        <p class="text-sm text-ink-500 mt-1">教材で詰まった時の補助線。AI の回答は参考情報です。</p>
    </div>

    <div class="mt-6">
        <x-card padding="lg">
            <x-empty-state
                icon="sparkles"
                title="まだ相談履歴はありません"
                description="気になる教材があれば、教材画面右下の AI ボタンから直接相談できます。"
            >
                <x-slot:action>
                    <x-button variant="primary" data-modal-trigger="new-ai-chat-modal">最初の相談を始める</x-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    </div>

    @include('ai-chat._partials.new-conversation-modal')
@endsection
