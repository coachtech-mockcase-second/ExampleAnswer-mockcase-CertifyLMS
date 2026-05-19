@extends('layouts.app')

@section('title', 'chat 対応')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'chat 対応'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">chat 対応</h1>
        <p class="text-sm text-ink-500 mt-1">担当受講生からのメッセージを資格ごとに確認できます。</p>
    </div>

    <div class="mt-6">
        <x-tabs :tabs="[
            'unread' => '未読あり',
            'all' => 'すべて',
        ]" :active="$filter['filter'] ?? 'unread'" param="filter" />
    </div>

    <form method="GET" class="mt-4 flex flex-wrap gap-3 items-end">
        <input type="hidden" name="filter" value="{{ $filter['filter'] ?? 'unread' }}">

        <div class="w-full max-w-xs">
            <x-form.label for="keyword">受講生名 / メールで絞り込み</x-form.label>
            <x-form.input
                id="keyword"
                name="keyword"
                type="search"
                placeholder="例: 山田 / yamada@..."
                :value="$filter['keyword'] ?? ''"
            />
        </div>

        <x-button type="submit" variant="secondary">
            <x-icon name="magnifying-glass" class="w-4 h-4" />
            絞り込む
        </x-button>
    </form>

    @if ($rooms->isEmpty())
        <div class="mt-6">
            @include('chat._partials.empty-message', [
                'title' => '該当する chat ルームはありません',
                'description' => 'フィルタを切り替えるか、検索条件を変えてください。',
            ])
        </div>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($rooms as $room)
                @php
                    $unread = $room->getAttribute('unread_count') ?? 0;
                    $student = $room->enrollment->user;
                    $studentName = $student->name;
                    $latest = $room->latestMessage;
                @endphp
                <a
                    href="{{ route('chat.show', $room) }}"
                    aria-label="{{ $studentName }} さんの chat ルームを開く"
                    class="block bg-surface-raised border border-[var(--border-subtle)] rounded-2xl px-5 py-4 hover:border-primary-200 hover:shadow-md transition"
                >
                    <div class="flex items-start gap-4">
                        <x-avatar :src="$student->avatar_url" :name="$studentName" size="md" />

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-display text-base font-bold text-ink-900">{{ $studentName }}</span>
                                <span class="text-xs text-ink-500">{{ $room->enrollment->certification->name }}</span>
                                @if ($unread > 0)
                                    <span
                                        class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-primary-600 text-white text-[11px] font-bold tabular-nums"
                                        aria-label="未読 {{ $unread }} 件"
                                    >{{ $unread }}</span>
                                @endif
                            </div>
                            @if ($latest !== null)
                                <p class="mt-2 text-sm text-ink-600 line-clamp-2">
                                    <span class="font-medium text-ink-700">{{ $latest->sender?->name ?? '送信者' }}:</span>
                                    {{ \Illuminate\Support\Str::limit($latest->body, 100) }}
                                </p>
                            @else
                                <p class="mt-2 text-sm text-ink-400 italic">まだメッセージがありません。</p>
                            @endif
                        </div>

                        <div class="text-right shrink-0 text-xs text-ink-500 font-mono">
                            @if ($room->last_message_at)
                                {{ $room->last_message_at->diffForHumans() }}
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">
            <x-paginator :paginator="$rooms" />
        </div>
    @endif
@endsection
