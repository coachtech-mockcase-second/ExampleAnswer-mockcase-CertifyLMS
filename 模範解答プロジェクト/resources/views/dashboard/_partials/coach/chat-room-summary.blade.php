@props([
    'rooms',
    'totalCount',
])

<x-card padding="md">
    <div class="flex items-baseline gap-2 mb-3">
        <h2 class="text-base font-bold text-ink-900 flex items-center gap-2">
            <x-icon name="chat-bubble-left-right" class="w-4 h-4 text-primary-600" />
            未対応 chat
        </h2>
        <span class="flex-1"></span>
        <a href="{{ route('coach.chat.index') }}" class="text-xs text-primary-700 hover:underline">
            すべて ({{ $totalCount ?? 0 }}) &rarr;
        </a>
    </div>

    @if ($rooms === null || $totalCount === null)
        @include('dashboard._partials.empty-state', ['message' => '未対応 chat を取得できませんでした。'])
    @elseif ($rooms->isEmpty())
        <p class="text-sm text-ink-500 py-2">未対応の chat はありません。素晴らしい応答ペースです。</p>
    @else
        <ul class="flex flex-col">
            @foreach ($rooms as $room)
                @php
                    $student = $room->enrollment?->user;
                    $latestMessage = $room->latestMessage;
                @endphp
                <li class="flex gap-2.5 py-2.5 border-b border-[var(--border-subtle)] last:border-b-0">
                    <x-avatar :name="$student?->name ?? '受講生'" size="md" />
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-baseline gap-2">
                            <p class="text-sm font-semibold text-ink-900 truncate">
                                {{ $student?->name ?? '受講生' }} · {{ $room->enrollment?->certification?->name }}
                            </p>
                            <span class="text-[11px] text-ink-500 font-mono flex-shrink-0">
                                {{ ($latestMessage?->created_at ?? $room->last_message_at ?? $room->created_at)->diffForHumans() }}
                            </span>
                        </div>
                        <p class="text-xs text-ink-700 mt-0.5 truncate">
                            {{ $latestMessage?->body ?? 'メッセージがあります' }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
