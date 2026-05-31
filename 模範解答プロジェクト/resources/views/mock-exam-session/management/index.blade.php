{{--
    受験セッションの管理一覧画面（管理側・閲覧専用）。受講生の模試受験状況を横断的に確認。
    構成: パンくず → ヘッダ（件数）→ フィルタフォーム（資格 select・受講生 ID・状態 select・合否 select）→ テーブル（0 件は empty-state）+ ページネーション
    テーブル列: 受講生 / 模試（+ 資格）/ 状態バッジ / 得点率 / 合否バッジ / 更新日時 / 詳細リンク
    フロント観点: JS なし。フィルタは GET フォーム送信、操作は詳細ページへのリンク遷移のみ。
--}}
@extends('layouts.app')

@section('title', '受験セッション閲覧')

@php
    use App\Enums\MockExamSessionStatus;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '受験セッション閲覧'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">受験セッション閲覧</h1>
        <p class="mt-1 text-sm text-ink-500">
            受講生の模試受験セッションを確認できます。
            <span class="font-semibold text-ink-700 tabular-nums">{{ $sessions->total() }} 件</span>
        </p>
    </div>

    {{-- フィルタ --}}
    <x-card class="mt-6" padding="sm" shadow="sm">
        <form novalidate method="GET" action="{{ route('admin.mock-exam-sessions.index') }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_140px_140px_auto]">
            <select name="certification_id" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 focus:outline-none focus:border-primary-500">
                <option value="">全資格</option>
                @foreach ($certifications as $c)
                    <option value="{{ $c->id }}" @selected($certificationId === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <input
                type="text"
                name="user_id"
                value="{{ $userId }}"
                placeholder="受講生 ID(任意)"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 placeholder:text-ink-400 focus:outline-none focus:border-primary-500"
            >
            <select name="status" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 focus:outline-none focus:border-primary-500">
                <option value="">状態すべて</option>
                @foreach (MockExamSessionStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($statusFilter === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
            <select name="pass" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 focus:outline-none focus:border-primary-500">
                <option value="">合否すべて</option>
                <option value="1" @selected($pass === '1')>合格</option>
                <option value="0" @selected($pass === '0')>不合格</option>
            </select>
            <x-button type="submit" variant="outline" size="md">絞り込む</x-button>
        </form>
    </x-card>

    {{-- 一覧 --}}
    <div class="mt-6">
        @if ($sessions->isEmpty())
            <x-empty-state
                icon="clipboard-document-check"
                title="該当するセッションがありません"
                description="フィルタを変更して再検索してください。"
            />
        @else
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>受講生</x-table.heading>
                        <x-table.heading>模試</x-table.heading>
                        <x-table.heading>状態</x-table.heading>
                        <x-table.heading class="text-right">得点率</x-table.heading>
                        <x-table.heading class="text-right">合否</x-table.heading>
                        <x-table.heading class="text-right">更新</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>
                @foreach ($sessions as $session)
                    <x-table.row>
                        <x-table.cell>
                            <span class="font-semibold text-ink-900">{{ $session->user?->name ?? '—' }}</span>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-sm text-ink-900">{{ $session->mockExam->title }}</span>
                            <p class="text-xs text-ink-500">{{ $session->mockExam->certification->name }}</p>
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge :variant="$session->status->color()" size="sm">{{ $session->status->label() }}</x-badge>
                        </x-table.cell>
                        <x-table.cell class="text-right tabular-nums">
                            @if ($session->score_percentage !== null)
                                {{ rtrim(rtrim((string) $session->score_percentage, '0'), '.') }}%
                            @else
                                <span class="text-ink-400">—</span>
                            @endif
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            @if ($session->pass === true)
                                <x-badge variant="success" size="sm">合格</x-badge>
                            @elseif ($session->pass === false)
                                <x-badge variant="danger" size="sm">不合格</x-badge>
                            @else
                                <span class="text-ink-400">—</span>
                            @endif
                        </x-table.cell>
                        <x-table.cell class="text-right tabular-nums text-xs text-ink-500">
                            {{ $session->updated_at?->format('Y-m-d H:i') }}
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <x-link-button href="{{ route('admin.mock-exam-sessions.show', $session) }}" variant="ghost" size="sm">
                                詳細
                            </x-link-button>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>

            <div class="mt-4">
                <x-paginator :paginator="$sessions" />
            </div>
        @endif
    </div>
@endsection
