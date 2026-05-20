@extends('layouts.app')

@section('title', 'chat 対応')

@section('content')
    @php
        $filter = $filters['filter'] ?? 'unread';
        $keyword = $filters['keyword'] ?? '';
    @endphp

    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'chat 対応'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">chat 対応</h1>
        <p class="text-sm text-ink-500 mt-1">担当受講生からのメッセージを資格ごとに確認できます。</p>
    </div>

    <form method="GET" class="mt-4 flex flex-wrap gap-3 items-end">
        <div class="flex gap-1">
            <button type="submit" name="filter" value="unread"
                class="px-3 py-2 rounded-md text-sm font-semibold transition {{ $filter === 'unread' ? 'bg-primary-600 text-white' : 'bg-ink-100 text-ink-700 hover:bg-ink-200' }}">
                未読あり
            </button>
            <button type="submit" name="filter" value="all"
                class="px-3 py-2 rounded-md text-sm font-semibold transition {{ $filter === 'all' ? 'bg-primary-600 text-white' : 'bg-ink-100 text-ink-700 hover:bg-ink-200' }}">
                すべて
            </button>
        </div>
        <div class="w-full max-w-xs">
            <x-form.input
                name="keyword"
                type="search"
                placeholder="受講生名 / メールで絞り込み"
                :value="$keyword"
            />
        </div>
    </form>

    <div class="mt-6">
        @include('chat-room._partials.empty-message', [
            'title' => '該当する chat ルームはありません',
            'description' => $filter === 'unread'
                ? '未読のメッセージはありません。「すべて」に切り替えると過去のルームを確認できます。'
                : 'フィルタや検索キーワードを変えて再度お試しください。',
        ])
    </div>
@endsection
