@php
    $viewer = auth()->user();
    $isAuthor = $viewer !== null && $reply->user_id === $viewer->id;
    $canUpdate = $viewer?->can('update', $reply) ?? false;
    $canDelete = $viewer?->can('delete', $reply) ?? false;
@endphp

<article id="reply-{{ $reply->id }}" class="bg-surface-raised border border-[var(--border-subtle,#E6EDEB)] rounded-2xl px-5 py-4">
    <header class="flex items-start gap-3">
        <x-avatar :src="$reply->user?->avatar_url" :name="$reply->user?->name ?? '?'" size="md" />
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-ink-900 text-sm">{{ $reply->user?->name ?? '不明' }}</span>
                @if ($reply->user?->role === \App\Enums\UserRole::Coach)
                    <x-badge variant="info" size="sm">コーチ</x-badge>
                @endif
                @if ($isAuthor)
                    <x-badge variant="gray" size="sm">自分の回答</x-badge>
                @endif
            </div>
            <div class="text-[11px] text-ink-500 font-mono mt-0.5">
                {{ $reply->created_at?->format('Y/m/d H:i') }}
                @if ($reply->updated_at && $reply->updated_at->ne($reply->created_at))
                    <span class="ml-2">(編集済: {{ $reply->updated_at->diffForHumans() }})</span>
                @endif
            </div>
        </div>

        @if ($canUpdate || $canDelete)
            <x-dropdown align="right">
                <x-slot:trigger>
                    <button type="button" class="p-1.5 rounded-md text-ink-500 hover:text-ink-900 hover:bg-ink-50 transition-colors" aria-label="この回答の操作メニュー">
                        <x-icon name="ellipsis-vertical" class="w-4 h-4" />
                    </button>
                </x-slot:trigger>
                @if ($canUpdate)
                    <x-dropdown.item :href="'#reply-edit-'.$reply->id" data-reply-toggle-edit="{{ $reply->id }}">
                        <x-icon name="pencil-square" class="w-4 h-4" />
                        編集
                    </x-dropdown.item>
                @endif
                @if ($canDelete)
                    <x-dropdown.item :href="route('qa-board.replies.destroy', ['thread' => $reply->qa_thread_id, 'reply' => $reply->id])" method="delete" variant="danger">
                        <x-icon name="trash" class="w-4 h-4" />
                        削除
                    </x-dropdown.item>
                @endif
            </x-dropdown>
        @endif
    </header>

    <div class="mt-3 text-sm leading-relaxed text-ink-800" data-reply-body-{{ $reply->id }}>
        {!! nl2br(e($reply->body)) !!}
    </div>

    @if ($canUpdate)
        <form
            method="POST"
            action="{{ route('qa-board.replies.update', ['thread' => $reply->qa_thread_id, 'reply' => $reply->id]) }}"
            id="reply-edit-{{ $reply->id }}"
            class="mt-4 hidden"
            data-reply-edit-form="{{ $reply->id }}"
        >
            @csrf
            @method('PATCH')
            <x-form.textarea
                name="body"
                label="回答を編集"
                :rows="5"
                :value="$reply->body"
                :maxlength="5000"
                :required="true"
            />
            <div class="flex items-center justify-end gap-3 mt-2">
                <button type="button" data-reply-cancel-edit="{{ $reply->id }}" class="text-sm text-ink-600 hover:text-ink-900">キャンセル</button>
                <x-button type="submit" variant="primary" size="sm">
                    <x-icon name="check" class="w-4 h-4" />
                    更新
                </x-button>
            </div>
        </form>
    @endif
</article>
