@extends('layouts.app')

@section('title', request()->routeIs('admin.*') ? 'chat 監査' : 'chat (コーチへ)')

@section('content')
    @php
        $viewer = auth()->user();
        $isAdminContext = request()->routeIs('admin.*');
        $viewerIsAdmin = $viewer?->role === \App\Enums\UserRole::Admin;
        $keyword = $filters['keyword'] ?? '';
    @endphp

    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => $isAdminContext ? 'chat 監査' : 'chat'],
    ]" />

    <div class="mt-4">
        @if ($viewerIsAdmin)
            <h1 class="text-2xl font-bold text-ink-900">chat 監査</h1>
            <p class="text-sm text-ink-500 mt-1">全 chat ルームを横断して閲覧できます。メッセージ送信は管理者には許可されません。</p>
        @else
            <h1 class="text-2xl font-bold text-ink-900">chat</h1>
            <p class="text-sm text-ink-500 mt-1">担当コーチへの相談をテキストでやり取りできます。資格ごとに 1 つのグループルームです。</p>
        @endif
    </div>

    @if ($viewerIsAdmin)
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
    @else
        <div class="mt-6">
            <x-card padding="lg">
                <x-empty-state
                    icon="chat-bubble-left-right"
                    title="まだ chat ルームはありません"
                    description="資格を受講登録すると、担当コーチを含む chat ルームが自動的に作成されます。"
                >
                    <x-slot:action>
                        <x-link-button href="{{ route('certifications.index') }}" variant="primary">
                            <x-icon name="magnifying-glass" class="w-4 h-4" />
                            資格カタログを見る
                        </x-link-button>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        </div>
    @endif
@endsection
