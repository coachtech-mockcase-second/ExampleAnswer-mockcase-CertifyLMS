{{--
    メッセージ吹き出しを縦に並べるリスト。各行は message-bubble partial。
    フロント観点: aria-live="polite" の live region で、JS が新着メッセージをこの <ul> に追記する。
--}}
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
