{{--
    通知詳細ページ。遷移先となる業務画面を持たない自己完結型通知（運営お知らせ等）の全文をここで閲覧する。
    構成: パンくず（通知一覧 → タイトル）→ カード（種別アイコン + タイトル + 受信日時 → 本文全文）→ 一覧への戻り導線。
    本文は改行保持（whitespace-pre-wrap）+ 自動エスケープで XSS 対策。JS なし: 閲覧専用、操作はリンク遷移のみ。
--}}
@extends('layouts.app')

@section('title', ($notification->data['title'] ?? '通知'))

@section('content')
    @php
        $data = is_array($notification->data) ? $notification->data : [];
        $title = $data['title'] ?? '通知';
        $type = $data['notification_type'] ?? null;
        $body = $data['body'] ?? ($data['message'] ?? '');
        $receivedAt = $notification->created_at?->format('Y/m/d H:i');
        $iconName = match ($type) {
            'chat_message_received' => 'chat-bubble-left-right',
            'qa_reply_received' => 'question-mark-circle',
            'meeting_reserved', 'meeting_canceled', 'meeting_reminder' => 'calendar-days',
            'admin_announcement' => 'megaphone',
            default => 'bell',
        };
        $sourceLabel = $type === 'admin_announcement' ? '運営からのお知らせ' : '通知';
    @endphp

    <div class="max-w-3xl mx-auto space-y-6">
        <header>
            <x-breadcrumb :items="[
                ['label' => '通知', 'href' => route('notifications.index')],
                ['label' => $title],
            ]" />
        </header>

        <x-card>
            <div class="flex items-start gap-3">
                <span class="mt-0.5 inline-flex w-10 h-10 items-center justify-center rounded-lg bg-primary-100 text-primary-700 shrink-0">
                    <x-icon :name="$iconName" class="w-5 h-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <h1 class="text-xl font-display font-bold text-ink-900">{{ $title }}</h1>
                    <p class="mt-1 text-sm text-ink-500">
                        {{ $sourceLabel }}@if ($receivedAt) ・{{ $receivedAt }}@endif
                    </p>
                </div>
            </div>

            <hr class="my-4 border-subtle">

            <div class="prose prose-sm max-w-none whitespace-pre-wrap text-ink-800">{{ $body }}</div>
        </x-card>

        <div>
            <x-link-button :href="route('notifications.index')" variant="ghost">← 通知一覧に戻る</x-link-button>
        </div>
    </div>
@endsection
