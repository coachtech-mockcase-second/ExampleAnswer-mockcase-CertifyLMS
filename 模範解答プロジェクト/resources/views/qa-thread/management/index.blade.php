@extends('layouts.app')

@section('title', '質問掲示板モデレーション')

@section('content')
    @php
        use App\Enums\CertificationStatus;
        use App\Enums\QaThreadStatus;

        $keyword = $filters['keyword'] ?? '';
        $currentStatus = $filters['status'] ?? '';
        $currentCertId = $filters['certification_id'] ?? '';
    @endphp

    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '質問掲示板モデレーション'],
    ]" />

    <div class="mt-4 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">質問掲示板モデレーション</h1>
            <p class="text-sm text-ink-500 mt-1 max-w-2xl">
                全資格 (公開停止含む) の質問スレッドを横断モデレーションできます。投稿内容の編集は管理者には許可されません。
                <span class="font-semibold text-ink-700 ml-1">{{ $threads->total() }} 件</span>
            </p>
        </div>
    </div>

    <x-card class="mt-6" padding="sm" shadow="sm">
        <form method="GET" action="{{ route('admin.qa-board.index') }}" class="grid gap-3 sm:grid-cols-[1fr_180px_180px_auto] items-end">
            <div>
                <x-form.label for="admin-qa-keyword">本文 / タイトル / 回答 LIKE 検索</x-form.label>
                <div class="relative">
                    <x-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-500" aria-hidden="true" />
                    <input
                        id="admin-qa-keyword"
                        type="search"
                        name="keyword"
                        value="{{ $keyword }}"
                        maxlength="100"
                        placeholder="例: ネットワーク / 平衡探索木"
                        class="w-full text-sm py-2 pl-9 pr-3 rounded-md bg-white border border-ink-200 placeholder:text-ink-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 transition-colors"
                    >
                </div>
            </div>

            <x-form.select
                name="certification_id"
                label="資格"
                :options="$certifications->mapWithKeys(fn ($c) => [$c->id => $c->name.($c->status !== CertificationStatus::Published ? ' ('.$c->status->label().')' : '')])->toArray()"
                :value="$currentCertId"
                placeholder="すべての資格"
            />

            <x-form.select
                name="status"
                label="解決状態"
                :options="['unresolved' => '未解決のみ', 'resolved' => '解決済のみ']"
                :value="$currentStatus"
                placeholder="すべての状態"
            />

            <div class="flex items-end gap-2 pb-[2px]">
                <x-button type="submit" variant="secondary">
                    <x-icon name="funnel" class="w-4 h-4" />
                    絞り込む
                </x-button>
            </div>
        </form>
    </x-card>

    @if ($threads->isEmpty())
        <div class="mt-6">
            <x-empty-state
                icon="question-mark-circle"
                title="該当するスレッドはありません"
                description="検索条件を変えて再検索してください。"
            />
        </div>
    @else
        <x-card class="mt-6" padding="none" shadow="sm">
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>タイトル</x-table.heading>
                        <x-table.heading>資格</x-table.heading>
                        <x-table.heading>投稿者</x-table.heading>
                        <x-table.heading>状態</x-table.heading>
                        <x-table.heading>回答</x-table.heading>
                        <x-table.heading>投稿日時</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>
                @foreach ($threads as $thread)
                    @php
                        $isResolved = $thread->status === QaThreadStatus::Resolved;
                    @endphp
                    <x-table.row>
                        <x-table.cell class="max-w-[320px]">
                            <a href="{{ route('admin.qa-board.show', $thread) }}" class="text-primary-700 hover:underline font-medium line-clamp-1">
                                {{ $thread->title }}
                            </a>
                        </x-table.cell>
                        <x-table.cell class="text-xs text-ink-600 whitespace-nowrap">{{ $thread->certification?->name ?? '—' }}</x-table.cell>
                        <x-table.cell class="text-xs whitespace-nowrap">{{ $thread->user?->name ?? '不明' }}</x-table.cell>
                        <x-table.cell>
                            @if ($isResolved)
                                <x-badge variant="success" size="sm">解決済</x-badge>
                            @else
                                <x-badge variant="warning" size="sm">未解決</x-badge>
                            @endif
                        </x-table.cell>
                        <x-table.cell class="font-mono text-xs tabular-nums">{{ (int) ($thread->replies_count ?? 0) }}</x-table.cell>
                        <x-table.cell class="font-mono text-xs text-ink-500 whitespace-nowrap">{{ $thread->created_at?->format('Y/m/d H:i') }}</x-table.cell>
                        <x-table.cell class="text-right">
                            <x-dropdown align="right">
                                <x-slot:trigger>
                                    <button type="button" class="p-1.5 rounded-md text-ink-500 hover:text-ink-900 hover:bg-ink-50" aria-label="スレッド操作">
                                        <x-icon name="ellipsis-vertical" class="w-4 h-4" />
                                    </button>
                                </x-slot:trigger>
                                <x-dropdown.item :href="route('admin.qa-board.show', $thread)">
                                    <x-icon name="eye" class="w-4 h-4" />
                                    詳細閲覧
                                </x-dropdown.item>
                                <x-dropdown.item :href="route('admin.qa-board.destroy', $thread)" method="delete" variant="danger">
                                    <x-icon name="trash" class="w-4 h-4" />
                                    モデレーション削除
                                </x-dropdown.item>
                            </x-dropdown>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </x-card>

        <div class="mt-6">
            <x-paginator :paginator="$threads" />
        </div>
    @endif
@endsection
