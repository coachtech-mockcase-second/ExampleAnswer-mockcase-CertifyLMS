@extends('layouts.app')

@section('title', '担当面談一覧')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '面談管理'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">担当面談一覧</h1>
        <p class="text-sm text-ink-500 mt-1">受講生からの予約が自動割当で入った面談を時系列で表示します。</p>
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
                    description="受講生が時刻スロットを選択すると自動的にここに表示されます。"
                />
            </x-card>
        </div>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($meetings as $meeting)
                <a href="{{ route('meetings.show', $meeting) }}"
                   class="block bg-surface-raised border border-[var(--border-subtle)] rounded-2xl px-5 py-4 hover:border-primary-200 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                @include('meetings._partials.status-badge', ['status' => $meeting->status])
                                <span class="text-xs text-ink-500">{{ $meeting->enrollment->certification->name }}</span>
                            </div>
                            <div class="mt-2 font-display text-lg font-bold text-ink-900 tabular-nums">
                                {{ $meeting->scheduled_at->translatedFormat('n月j日 (D) H:i') }}
                                <span class="text-sm font-medium text-ink-500 ml-1">〜 {{ $meeting->scheduled_at->copy()->addHour()->format('H:i') }}</span>
                            </div>
                            <p class="mt-1 text-sm text-ink-700 line-clamp-2">{{ $meeting->topic }}</p>
                            <div class="mt-2 text-xs text-ink-500">
                                受講生: {{ $meeting->student->name }}
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
