{{--
    受講生の面談履歴一覧画面。登録中の全資格を横断して面談を時系列表示する。
    構成: パンくず → 予約/履歴タブ(+残り面談回数) → 見出し → 状態フィルタタブ(今後/過去/すべて) → 面談カード一覧(ステータスバッジ + 日時 + 相談内容 + 担当コーチ) or 空状態 → ページネーション
    JS なし(タブ・カードはリンク遷移、ページャもリンク)。
--}}
@extends('layouts.app')

@section('title', '面談履歴')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '面談'],
    ]" />

    @include('meeting._partials.nav-tabs', ['meetingsRemaining' => $meetingsRemaining])

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">面談履歴</h1>
        <p class="text-sm text-ink-500 mt-1">登録中の全資格を横断して表示しています。</p>
    </div>

    <div class="mt-6">
        <x-tabs :tabs="[
            'upcoming' => '今後の予定',
            'past' => '過去の面談',
            'all' => 'すべて',
        ]" :active="$filter" />
    </div>

    @if ($meetings->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="calendar-days"
                    title="該当する面談はありません"
                    description="フィルタを切り替えるか、新しく予約してください。"
                >
                    <x-slot:action>
                        <x-link-button href="{{ route('meetings.fallback.create') }}" variant="primary">
                            <x-icon name="plus" class="w-4 h-4" />
                            予約する
                        </x-link-button>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        </div>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($meetings as $meeting)
                <a href="{{ route('meetings.show', $meeting) }}"
                   class="block bg-surface-raised border border-subtle rounded-2xl px-5 py-4 hover:border-primary-200 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                @include('meeting._partials.status-badge', ['status' => $meeting->status])
                                <span class="text-xs text-ink-500">{{ $meeting->enrollment->certification->name }}</span>
                            </div>
                            <div class="mt-2 font-display text-lg font-bold text-ink-900 tabular-nums">
                                {{ $meeting->scheduled_at->translatedFormat('n月j日 (D) H:i') }}
                                <span class="text-sm font-medium text-ink-500 ml-1">〜 {{ $meeting->scheduled_at->copy()->addHour()->format('H:i') }}</span>
                            </div>
                            <p class="mt-1 text-sm text-ink-700 line-clamp-2">{{ $meeting->topic }}</p>
                            <div class="mt-2 text-xs text-ink-500">
                                担当コーチ: {{ $meeting->coach->name }}
                            </div>
                        </div>
                        <div class="shrink-0 text-ink-400">
                            <x-icon name="chevron-right" class="w-5 h-5" />
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            <x-paginator :paginator="$meetings->withQueryString()" />
        </div>
    @endif
@endsection
