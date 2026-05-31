{{--
    フル画面 AI 相談の入力欄（composer）。
    構成: 自動リサイズ textarea + 送信ボタン → 補助行（⌘+Enter 送信ヒント）。
    フロント観点: 通常のフォーム送信ではなく素の JS が data-* 属性を読んで非同期送信し、応答を画面に追記する。⌘+Enter ショートカット送信。
--}}
@php
    /** @var \App\Models\AiChatConversation $conversation */
@endphp

<form novalidate data-ai-chat-form
    data-conversation-id="{{ $conversation->id }}"
    data-store-url="{{ route('ai-chat.conversations.messages.store', $conversation) }}"
    class="max-w-[760px] mx-auto w-full">
    @csrf

    <div class="flex gap-2 items-end bg-white border border-default rounded-2xl pl-4 pr-1.5 py-1.5 shadow-sm focus-within:border-secondary-400 focus-within:shadow-[0_0_0_4px_rgba(124,58,237,0.10)] transition">
        <textarea name="content"
            rows="1"
            maxlength="2000"
            placeholder="AI に質問してみよう..."
            data-ai-chat-textarea
            class="flex-1 bg-transparent border-0 outline-none resize-none text-sm leading-relaxed text-ink-900 placeholder:text-ink-400 py-2 max-h-[140px] min-h-[24px]"
            aria-label="AI への質問入力"></textarea>
        <button type="submit"
            data-ai-chat-submit
            aria-label="送信"
            class="w-[38px] h-[38px] rounded-xl bg-secondary-600 hover:bg-secondary-700 text-white inline-flex items-center justify-center transition shadow-md self-end disabled:opacity-50 disabled:cursor-not-allowed">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="w-4 h-4" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
            </svg>
        </button>
    </div>

    <div class="flex items-center gap-3 mt-2 px-4 text-[11px] text-ink-500">
        <span>⌘+Enter で送信 · AI の回答は参考情報です</span>
    </div>
</form>
