@extends('layouts.app')

@section('title', 'chat 監査')

@section('content')
    @php
        $keyword = $filters['keyword'] ?? '';
    @endphp

    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'chat 監査'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">chat 監査</h1>
        <p class="text-sm text-ink-500 mt-1">全 chat ルームを横断して閲覧できます。メッセージ送信は管理者には許可されません。</p>
    </div>

    <form method="GET" class="mt-6 flex flex-wrap gap-3 items-end">
        <div class="w-full max-w-xs">
            <x-form.label for="keyword">受講生名 / メールで絞り込み</x-form.label>
            <x-form.input
                id="keyword"
                name="keyword"
                type="search"
                placeholder="例: 山田 / yamada@..."
                :value="$keyword"
            />
        </div>
        <x-button type="submit" variant="secondary">
            <x-icon name="magnifying-glass" class="w-4 h-4" />
            絞り込む
        </x-button>
    </form>

    <div class="mt-6">
        @include('chat-room._partials.empty-message', [
            'title' => '該当する chat ルームはありません',
            'description' => '検索条件を変えるか、受講登録が増えるまでお待ちください。',
        ])
    </div>
@endsection
