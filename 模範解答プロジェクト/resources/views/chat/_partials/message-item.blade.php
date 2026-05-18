@php
    $authId = auth()->id();
    $isSelf = $message->sender_user_id === $authId;
    $senderRole = $message->sender?->role;
    $roleLabel = $senderRole?->label() ?? '';
@endphp

<div
    data-message-id="{{ $message->id }}"
    class="flex gap-3 {{ $isSelf ? 'flex-row-reverse' : '' }}"
>
    <x-avatar :name="$message->sender?->name ?? '?'" size="sm" />

    <div class="flex flex-col gap-1 max-w-[75%] {{ $isSelf ? 'items-end' : 'items-start' }}">
        <div class="px-4 py-2.5 rounded-2xl text-sm leading-relaxed whitespace-pre-wrap break-words {{ $isSelf ? 'bg-primary-600 text-white' : 'bg-surface-raised text-ink-900 border border-[var(--border-subtle)]' }}">{{ $message->body }}</div>
        <div class="text-[11px] text-ink-500 flex items-center gap-2">
            <span class="font-medium">{{ $message->sender?->name ?? '送信者' }}</span>
            @if (! $isSelf && $roleLabel !== '')
                <span class="text-ink-400">· {{ $roleLabel }}</span>
            @endif
            <span class="text-ink-400 font-mono">· {{ $message->created_at->translatedFormat('n月j日 H:i') }}</span>
        </div>
    </div>
</div>
