@php
    /** @var \Illuminate\Notifications\DatabaseNotification $notification */
    $data = is_array($notification->data) ? $notification->data : [];
    $title = $data['title'] ?? '通知';
    $message = $data['message'] ?? ($data['body_preview'] ?? '');
    $createdRelative = $notification->created_at?->diffForHumans() ?? '';
    $type = $data['notification_type'] ?? null;
    $isUnread = $notification->read_at === null;
    $iconName = match ($type) {
        'chat_message_received' => 'chat-bubble-left-right',
        'qa_reply_received' => 'question-mark-circle',
        'completion_approved' => 'academic-cap',
        'meeting_reserved', 'meeting_canceled', 'meeting_reminder' => 'calendar-days',
        'admin_announcement' => 'megaphone',
        default => 'bell',
    };
@endphp

<li @class([
    'group',
    'bg-primary-50/30' => $isUnread,
])>
    <form
        method="POST"
        action="{{ route('notifications.markAsRead', $notification) }}"
        class="block"
    >
        @csrf
        <button
            type="submit"
            class="w-full flex items-start gap-3 px-4 sm:px-6 py-4 hover:bg-ink-50 transition-colors text-left"
        >
            <span @class([
                'mt-1 inline-flex w-9 h-9 items-center justify-center rounded-lg shrink-0',
                'bg-primary-100 text-primary-700' => $isUnread,
                'bg-ink-100 text-ink-500' => ! $isUnread,
            ])>
                <x-icon :name="$iconName" class="w-4 h-4" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-start gap-2">
                    @if ($isUnread)
                        <span class="mt-1.5 inline-block w-2 h-2 rounded-full bg-primary-600 shrink-0" aria-label="未読"></span>
                    @endif
                    <p @class([
                        'text-sm truncate',
                        'font-semibold text-ink-900' => $isUnread,
                        'text-ink-700' => ! $isUnread,
                    ])>{{ $title }}</p>
                </div>
                @if ($message)
                    <p class="mt-1 text-xs text-ink-600 line-clamp-2">{{ $message }}</p>
                @endif
                <p class="mt-1.5 text-[11px] text-ink-500">{{ $createdRelative }}</p>
            </div>
        </button>
    </form>
</li>
