{{--
    通知一覧ページ。
    構成: ヘッダ(未読件数 + 全件既読ボタン) → タブ(全件 / 未読のみ) → 通知行リスト(0 件は empty-state) → ページネーション。
    全件既読はフォーム POST(JS なし)。各行は notification-row partial。
--}}
@extends('layouts.app')

@section('title', '通知')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-display font-bold text-ink-900">通知</h1>
                <p class="mt-1 text-sm text-ink-600">
                    @if ($unreadCount > 0)
                        現在 <span class="font-semibold text-primary-700">{{ $unreadCount }}</span> 件の未読通知があります。
                    @else
                        未読の通知はありません。
                    @endif
                </p>
            </div>
            @if ($unreadCount > 0)
                <form novalidate method="POST" action="{{ route('notifications.markAllAsRead') }}">
                    @csrf
                    <x-button type="submit" variant="outline" size="sm">全件既読にする</x-button>
                </form>
            @endif
        </header>

        <x-tabs
            :tabs="['all' => '全件', 'unread' => '未読のみ']"
            :active="$tab"
        />

        <x-card padding="none" shadow="sm">
            @if ($notifications->isEmpty())
                <div class="px-6 py-12">
                    <x-empty-state
                        icon="bell-slash"
                        title="通知はありません"
                        description="新しい通知が届くとここに表示されます。"
                    />
                </div>
            @else
                <ul class="divide-y divide-subtle">
                    @foreach ($notifications as $notification)
                        @include('notifications._partials.notification-row', ['notification' => $notification])
                    @endforeach
                </ul>
            @endif
        </x-card>

        @if ($notifications->hasPages())
            <div class="flex justify-center">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection
