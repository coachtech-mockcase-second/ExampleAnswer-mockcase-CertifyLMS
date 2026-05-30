{{--
    chat メッセージ送信フォーム partial。props 相当: room。
    構成: 本文 textarea(文字数上限あり) → 補足文 + 送信ボタン。
    フロント挙動: 通常の POST 送信に加え、素の JS が ⌘/Ctrl + Enter での送信や送信後の追記を扱う(data-chat-composer フック)。
--}}
<form
    method="POST"
    action="{{ route('chat.storeMessage', $room) }}"
    class="border-t border-subtle px-6 py-4 bg-surface-raised"
    aria-label="メッセージ送信フォーム"
>
    @csrf

    <x-form.textarea
        name="body"
        rows="2"
        maxlength="2000"
        placeholder="メッセージを入力... (Enter で改行 / ⌘ + Enter または Ctrl + Enter で送信)"
        aria-label="メッセージ本文"
        data-chat-composer
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
