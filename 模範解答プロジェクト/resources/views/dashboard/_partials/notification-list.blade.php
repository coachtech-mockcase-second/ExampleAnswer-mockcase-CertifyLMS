@props([
    'notifications',
    'unreadCount' => 0,
])

<x-card padding="md">
    <div class="flex items-baseline gap-2 mb-3">
        <h2 class="text-base font-bold text-ink-900 flex items-center gap-2">
            <x-icon name="bell" class="w-4 h-4 text-ink-600" />
            直近通知
        </h2>
        @if ($unreadCount > 0)
            <span class="text-xs text-ink-500 font-medium">{{ $unreadCount }} 件 未読</span>
        @endif
        <span class="flex-1"></span>
        <a href="{{ route('notifications.index') }}" class="text-xs text-primary-700 hover:underline">すべて &rarr;</a>
    </div>

    @if ($notifications->isEmpty())
        <p class="text-sm text-ink-500 py-2">まだ通知はありません。</p>
    @else
        <ul class="flex flex-col">
            @foreach ($notifications as $notification)
                @php
                    $data = $notification->data ?? [];
                    $title = $data['title'] ?? ($data['message'] ?? '通知');
                    $isUnread = $notification->read_at === null;
                @endphp
                <li class="flex gap-2.5 py-2 border-b border-[var(--border-subtle)] last:border-b-0">
                    <span class="inline-flex w-7 h-7 flex-shrink-0 items-center justify-center rounded-full {{ $isUnread ? 'bg-primary-100 text-primary-700' : 'bg-ink-100 text-ink-500' }}">
                        <x-icon name="bell" class="w-3 h-3" />
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-ink-900 leading-relaxed">{{ $title }}</p>
                        <p class="text-[11px] text-ink-500 mt-0.5">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
