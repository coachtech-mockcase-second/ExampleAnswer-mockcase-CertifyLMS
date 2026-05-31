@extends('layouts.app')

@section('title', '質問掲示板')

@section('content')
    {{-- 質問掲示板の一覧。見出し + 絞り込みフォーム + スレッドカード一覧 + ページネーションで構成。$isAdminContext で公開 / 管理者の表示を出し分ける --}}
    @php
        $viewer = auth()->user();
        $isAdminContext = request()->routeIs('admin.*');
        $indexRoute = $isAdminContext ? 'admin.qa-board.index' : 'qa-board.index';
        $showRoute = $isAdminContext ? 'admin.qa-board.show' : 'qa-board.show';
        $canPost = $viewer?->can('create', \App\Models\QaThread::class) ?? false;
    @endphp

    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => $isAdminContext ? '質問掲示板モデレーション' : '質問掲示板'],
    ]" />

    <div class="mt-4 flex items-start justify-between gap-4 flex-wrap">
        <div>
            @if ($isAdminContext)
                <p class="text-[11px] uppercase tracking-wider text-primary-700 font-semibold">モデレーション</p>
                <h1 class="text-2xl font-bold text-ink-900 mt-1">質問掲示板モデレーション</h1>
                <p class="text-sm text-ink-500 mt-1 max-w-2xl">
                    全資格(公開停止含む)の質問スレッドを横断モデレーションできます。投稿内容の編集は管理者には許可されません。
                    <span class="font-semibold text-ink-700 ml-1">{{ $threads->total() }} 件</span>
                </p>
            @else
                <p class="text-[11px] uppercase tracking-wider text-primary-700 font-semibold">学習相談 · 公開Q&A</p>
                <h1 class="text-2xl font-bold text-ink-900 mt-1">質問掲示板</h1>
                <p class="text-sm text-ink-500 mt-1 max-w-2xl">
                    他の受講生も閲覧できる公開掲示板です。AI や 1on1 chat とは別の動線です。検索で過去の質問を活用しましょう。
                </p>
            @endif
        </div>
        @if ($canPost)
            <x-link-button href="{{ route('qa-board.create') }}" variant="primary">
                <x-icon name="plus" class="w-4 h-4" />
                質問を投稿
            </x-link-button>
        @endif
    </div>

    <div class="mt-6">
        @include('qa-thread._filter', [
            'filters' => $filters,
            'certifications' => $certifications,
            'indexRoute' => $indexRoute,
            'publishedStatus' => $publishedStatus,
        ])
    </div>

    @if ($threads->isEmpty())
        <div class="mt-6">
            <x-empty-state
                icon="question-mark-circle"
                title="該当する質問はまだありません"
                description="フィルタ条件を変更するか、新しい質問を投稿してみましょう。"
            >
                @if ($canPost)
                    <x-slot:action>
                        <x-link-button href="{{ route('qa-board.create') }}" variant="primary">
                            <x-icon name="plus" class="w-4 h-4" />
                            最初の質問を投稿
                        </x-link-button>
                    </x-slot:action>
                @endif
            </x-empty-state>
        </div>
    @else
        <div class="mt-6 flex flex-col gap-2.5">
            @foreach ($threads as $thread)
                @include('qa-thread._thread-card', [
                    'thread' => $thread,
                    'showRoute' => $showRoute,
                    'publishedStatus' => $publishedStatus,
                ])
            @endforeach
        </div>

        <div class="mt-6">
            <x-paginator :paginator="$threads" />
        </div>
    @endif
@endsection
