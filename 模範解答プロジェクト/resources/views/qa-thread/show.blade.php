@extends('layouts.app')

@section('title', $thread->title)

@section('content')
    {{-- スレッド詳細。スレッド本体カード + 回答一覧 + 回答投稿フォームの 3 ブロック構成。操作は JS を使わずリンク / フォームで行う --}}
    @php
        use App\Enums\QaThreadStatus;

        $viewer = auth()->user();
        $isAdminContext = request()->routeIs('admin.*');
        $indexRoute = $isAdminContext ? 'admin.qa-board.index' : 'qa-board.index';
        $destroyRoute = $isAdminContext ? 'admin.qa-board.destroy' : 'qa-board.destroy';
        $isResolved = $thread->status === QaThreadStatus::Resolved;
        $canUpdate = $viewer?->can('update', $thread) ?? false;
        $canDelete = $viewer?->can('delete', $thread) ?? false;
        $canResolve = ! $isResolved && ($viewer?->can('resolve', $thread) ?? false);
        $canUnresolve = $isResolved && ($viewer?->can('unresolve', $thread) ?? false);
        $replies = $thread->replies;
    @endphp

    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => $isAdminContext ? '質問掲示板モデレーション' : '質問掲示板', 'href' => route($indexRoute)],
        ['label' => $thread->title],
    ]" />

    <x-card class="mt-4" padding="lg" shadow="sm">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <x-badge variant="gray">{{ $thread->certification?->name ?? '資格未設定' }}</x-badge>
                    @if ($isResolved)
                        <x-badge variant="success">✓ 解決済</x-badge>
                    @else
                        @if ($thread->replies_count !== null && $thread->replies_count === 0)
                            <x-badge variant="danger">未回答</x-badge>
                        @else
                            <x-badge variant="warning">未解決</x-badge>
                        @endif
                    @endif
                </div>
                <h1 class="text-2xl font-bold text-ink-900 mt-3 leading-snug">{{ $thread->title }}</h1>
                <div class="flex items-center gap-3 mt-3 text-sm">
                    <x-avatar :src="$thread->user?->avatar_url" :name="$thread->user?->name ?? '?'" size="sm" />
                    <span class="font-medium text-ink-700">{{ $thread->user?->name ?? '不明' }}</span>
                    <span class="inline-block w-1 h-1 rounded-full bg-ink-300"></span>
                    <span class="text-ink-500 font-mono text-xs">{{ $thread->created_at?->format('Y/m/d H:i') }}</span>
                    @if ($isResolved && $thread->resolved_at)
                        <span class="inline-block w-1 h-1 rounded-full bg-ink-300"></span>
                        <span class="text-success-700 text-xs">{{ $thread->resolved_at->format('Y/m/d H:i') }} に解決</span>
                    @endif
                </div>
            </div>

            @if ($canUpdate || $canDelete || $canResolve || $canUnresolve)
                <div class="flex items-center gap-2 flex-wrap">
                    @if ($canResolve)
                        <form method="POST" action="{{ route('qa-board.resolve', $thread) }}">
                            @csrf
                            <x-button type="submit" variant="primary" size="sm">
                                <x-icon name="check-circle" class="w-4 h-4" />
                                解決済にする
                            </x-button>
                        </form>
                    @endif
                    @if ($canUnresolve)
                        <form method="POST" action="{{ route('qa-board.unresolve', $thread) }}">
                            @csrf
                            <x-button type="submit" variant="outline" size="sm">
                                <x-icon name="arrow-uturn-left" class="w-4 h-4" />
                                未解決に戻す
                            </x-button>
                        </form>
                    @endif

                    {{-- 編集はリンク遷移 --}}
                    @if ($canUpdate)
                        <x-link-button href="{{ route('qa-board.edit', $thread) }}" variant="outline" size="sm">
                            <x-icon name="pencil-square" class="w-4 h-4" />
                            編集
                        </x-link-button>
                    @endif
                    {{-- 削除はフォーム送信。onsubmit の confirm() で誤操作を防ぐ（HTML 標準、JS ファイル不要）--}}
                    @if ($canDelete)
                        <form method="POST" action="{{ route($destroyRoute, $thread) }}" onsubmit="return confirm('この質問を削除しますか？');">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="danger" size="sm">
                                <x-icon name="trash" class="w-4 h-4" />
                                削除
                            </x-button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        <div class="mt-6 text-[15px] leading-relaxed text-ink-800">
            {!! nl2br(e($thread->body)) !!}
        </div>
    </x-card>

    <section class="mt-6" aria-labelledby="qa-replies-heading">
        <div class="flex items-center justify-between">
            <h2 id="qa-replies-heading" class="text-lg font-bold text-ink-900">
                回答
                <span class="ml-2 text-sm font-normal text-ink-500 font-mono">{{ $replies->count() }} 件</span>
            </h2>
        </div>

        @if ($replies->isEmpty())
            <div class="mt-3">
                <x-empty-state
                    icon="chat-bubble-bottom-center-text"
                    title="まだ回答はありません"
                    description="最初の回答者になりましょう。"
                />
            </div>
        @else
            <div class="mt-3 flex flex-col gap-3">
                @foreach ($replies as $reply)
                    @include('qa-thread._reply', ['reply' => $reply, 'isAdminContext' => $isAdminContext])
                @endforeach
            </div>
        @endif
    </section>

    <section class="mt-6" aria-label="回答投稿フォーム">
        @include('qa-thread._reply-form', ['thread' => $thread])
    </section>
@endsection
