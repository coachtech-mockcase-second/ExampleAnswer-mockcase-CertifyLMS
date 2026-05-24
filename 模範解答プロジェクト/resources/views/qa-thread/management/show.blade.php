@extends('layouts.app')

@section('title', '【モデレーション】'.$thread->title)

@section('content')
    @php
        use App\Enums\QaThreadStatus;

        $isResolved = $thread->status === QaThreadStatus::Resolved;
        $replies = $thread->replies;
    @endphp

    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '質問掲示板モデレーション', 'href' => route('admin.qa-board.index')],
        ['label' => $thread->title],
    ]" />

    <x-card class="mt-4" padding="lg" shadow="sm">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <x-badge variant="gray">{{ $thread->certification?->name ?? '—' }}</x-badge>
                    @if ($isResolved)
                        <x-badge variant="success">解決済</x-badge>
                    @else
                        <x-badge variant="warning">未解決</x-badge>
                    @endif
                </div>
                <h1 class="text-2xl font-bold text-ink-900 mt-3 leading-snug">{{ $thread->title }}</h1>
                <div class="flex items-center gap-3 mt-3 text-sm">
                    <x-avatar :src="$thread->user?->avatar_url" :name="$thread->user?->name ?? '?'" size="sm" />
                    <span class="font-medium text-ink-700">{{ $thread->user?->name ?? '不明' }}</span>
                    <span class="inline-block w-1 h-1 rounded-full bg-ink-300"></span>
                    <span class="text-ink-500 font-mono text-xs">{{ $thread->created_at?->format('Y/m/d H:i') }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.qa-board.destroy', $thread) }}">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger" size="sm">
                    <x-icon name="trash" class="w-4 h-4" />
                    モデレーション削除
                </x-button>
            </form>
        </div>

        <div class="mt-6 text-[15px] leading-relaxed text-ink-800">
            {!! nl2br(e($thread->body)) !!}
        </div>
    </x-card>

    <section class="mt-6" aria-labelledby="admin-qa-replies-heading">
        <h2 id="admin-qa-replies-heading" class="text-lg font-bold text-ink-900">
            回答
            <span class="ml-2 text-sm font-normal text-ink-500 font-mono">{{ $replies->count() }} 件</span>
        </h2>

        @if ($replies->isEmpty())
            <div class="mt-3">
                <x-empty-state
                    icon="chat-bubble-bottom-center-text"
                    title="回答はまだありません"
                    description="まだ回答が投稿されていません。"
                />
            </div>
        @else
            <div class="mt-3 flex flex-col gap-3">
                @foreach ($replies as $reply)
                    <article class="bg-surface-raised border rounded-2xl px-5 py-4 border-[var(--border-subtle,#E6EDEB)]">
                        <header class="flex items-start gap-3">
                            <x-avatar :src="$reply->user?->avatar_url" :name="$reply->user?->name ?? '?'" size="md" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-ink-900 text-sm">{{ $reply->user?->name ?? '不明' }}</span>
                                    @if ($reply->user?->role === \App\Enums\UserRole::Coach)
                                        <x-badge variant="info" size="sm">コーチ</x-badge>
                                    @endif
                                </div>
                                <div class="text-[11px] text-ink-500 font-mono mt-0.5">
                                    {{ $reply->created_at?->format('Y/m/d H:i') }}
                                </div>
                            </div>

                            <form method="POST" action="{{ route('admin.qa-board.replies.destroy', $reply) }}">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="danger" size="sm">
                                    <x-icon name="trash" class="w-4 h-4" />
                                    削除
                                </x-button>
                            </form>
                        </header>
                        <div class="mt-3 text-sm leading-relaxed text-ink-800">
                            {!! nl2br(e($reply->body)) !!}
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
