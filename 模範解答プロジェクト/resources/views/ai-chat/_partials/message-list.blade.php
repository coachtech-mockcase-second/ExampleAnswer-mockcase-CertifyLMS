@php
    /** @var \App\Models\AiChatConversation $conversation */
@endphp

<ul role="log"
    aria-live="polite"
    aria-atomic="false"
    aria-label="AI 相談メッセージ"
    class="flex flex-col gap-4 max-w-[760px] mx-auto w-full"
    data-message-list>
    @foreach ($conversation->messages as $message)
        @include('ai-chat._partials.message-bubble', ['message' => $message])
    @endforeach
</ul>
