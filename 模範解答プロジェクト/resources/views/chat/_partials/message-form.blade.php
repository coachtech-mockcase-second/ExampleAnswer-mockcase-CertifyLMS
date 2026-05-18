<form
    method="POST"
    action="{{ route('chat.storeMessage', $room) }}"
    class="border-t border-[var(--border-subtle)] px-6 py-4 bg-surface-raised"
    aria-label="メッセージ送信フォーム"
>
    @csrf

    <x-form.textarea
        name="body"
        rows="2"
        maxlength="2000"
        placeholder="メッセージを入力... (Shift + Enter で改行、Enter で送信)"
        aria-label="メッセージ本文"
        required
    />

    @error('body')
        <x-form.error :messages="$message" class="mt-2" />
    @enderror

    <div class="mt-3 flex items-center justify-between gap-3 flex-wrap">
        <p class="text-xs text-ink-500">最大 2000 文字。テキストのみ送信できます。</p>
        <x-button type="submit" variant="primary">
            <x-icon name="paper-airplane" class="w-4 h-4" />
            送信
        </x-button>
    </div>
</form>
