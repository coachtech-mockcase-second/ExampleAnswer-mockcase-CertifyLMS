{{--
    管理者お知らせの詳細画面（管理者向け、配信済み内容の閲覧専用）。
    構成: ヘッダ（パンくず + タイトル）→ カード（配信対象 / 配信件数 / 配信日時 / 配信者のメタ情報 → 本文）→ 一覧戻り / 新規作成の導線。
    本文は改行保持（whitespace-pre-wrap）+ 自動エスケープで XSS 対策。
    JS なし: 閲覧専用、操作はリンク遷移のみ（配信後の編集・取消は不可）。
--}}
@extends('layouts.app')

@section('title', '管理者お知らせ — 詳細')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <header>
            <x-breadcrumb :items="[
                ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
                ['label' => '管理者お知らせ', 'href' => route('admin.announcements.index')],
                ['label' => $announcement->title],
            ]" />
            <h1 class="mt-2 text-2xl font-display font-bold text-ink-900">{{ $announcement->title }}</h1>
        </header>

        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs uppercase tracking-wider text-ink-500">配信対象</p>
                    <div class="mt-1 flex items-center gap-2">
                        <x-badge variant="info" size="sm">{{ $announcement->target_type->label() }}</x-badge>
                        @if ($announcement->targetCertification)
                            <span class="text-sm text-ink-700">{{ $announcement->targetCertification->name }}</span>
                        @elseif ($announcement->targetUser)
                            <span class="text-sm text-ink-700">{{ $announcement->targetUser->name }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-ink-500">配信件数</p>
                    <p class="mt-1 text-2xl font-display font-bold text-ink-900 tnum">{{ $announcement->dispatched_count }} 件</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-ink-500">配信日時</p>
                    <p class="mt-1 text-sm text-ink-900">{{ $announcement->dispatched_at?->format('Y/m/d H:i') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-ink-500">配信者</p>
                    <p class="mt-1 text-sm text-ink-900">{{ $announcement->createdBy?->name ?? '—' }}</p>
                </div>
            </div>

            <hr class="my-4 border-subtle">

            <div>
                <p class="text-xs uppercase tracking-wider text-ink-500 mb-2">本文</p>
                <div class="prose prose-sm max-w-none whitespace-pre-wrap text-ink-800">{{ $announcement->body }}</div>
            </div>
        </x-card>

        <div class="flex justify-between">
            <x-link-button :href="route('admin.announcements.index')" variant="ghost">← 一覧に戻る</x-link-button>
            <x-link-button :href="route('admin.announcements.create')" variant="outline">新しいお知らせを作成</x-link-button>
        </div>
    </div>
@endsection
