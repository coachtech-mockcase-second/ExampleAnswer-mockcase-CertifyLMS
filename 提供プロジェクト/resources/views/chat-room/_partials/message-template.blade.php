{{--
    リアルタイム受信したメッセージを描画するための複製元 template(画面には非表示)。
    素の JS が中身を clone し、本文 / 送信者名 / ロール / 時刻の各スロット(data-* 属性)に値を差し込んで一覧へ追記する。
    通常の吹き出し(message-item)と同じ見た目に揃えてある。
--}}
<template id="chat-message-template">
    <div data-message-root class="flex gap-3">
        <div data-avatar-slot class="w-8 h-8 rounded-full bg-secondary-600 text-white text-xs font-semibold flex items-center justify-center shrink-0"></div>

        <div data-body-wrap class="flex flex-col gap-1 max-w-[75%] items-start">
            <div
                data-body
                data-bubble
                class="px-4 py-2.5 rounded-2xl text-sm leading-relaxed whitespace-pre-wrap break-words bg-surface-raised text-ink-900 border border-subtle"
            ></div>
            <div class="text-[11px] text-ink-500 flex items-center gap-2">
                <span data-sender-name class="font-medium"></span>
                <span data-sender-role class="text-ink-400"></span>
                <span data-created-at class="text-ink-400 font-mono"></span>
            </div>
        </div>
    </div>
</template>
