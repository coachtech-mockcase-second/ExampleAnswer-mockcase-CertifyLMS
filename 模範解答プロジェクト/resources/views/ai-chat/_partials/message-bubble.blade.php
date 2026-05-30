{{--
    メッセージ 1 件分の吹き出し（$message を受け取る）。
    構成: アバター + 吹き出し本体（本文 / 応答待ちのローディングドット / エラー文 + 再送信ボタン）+ メタ行（時刻・応答時間・トークン数）。
    フロント観点: 自分の発言と AI の発言で左右と配色を出し分け。AI 文は素の JS が Markdown をサニタイズ済 HTML に変換して描画。応答待ちはアニメーションのドット表示。エラー時の「再送信」は data-* を JS が拾って非同期で再実行。
--}}
@php
    /** @var \App\Models\AiChatMessage $message */
    $isMe = $message->role === \App\Enums\AiChatMessageRole::User;
    $isError = $message->status === \App\Enums\AiChatMessageStatus::Error;
    $isPending = in_array($message->status, [
        \App\Enums\AiChatMessageStatus::Pending,
        \App\Enums\AiChatMessageStatus::Streaming,
    ], true);
    $viewerName = auth()->user()?->name ?? 'YOU';
    $initials = mb_substr($viewerName, 0, 2);
@endphp

<li class="flex gap-3 {{ $isMe ? 'flex-row-reverse ml-auto max-w-[80%]' : '' }}"
    data-message-id="{{ $message->id }}"
    data-message-status="{{ $message->status->value }}">
    @if ($isMe)
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-success-600 text-white text-[11px] font-bold flex-shrink-0">
            {{ $initials }}
        </span>
    @else
        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-secondary-600 text-white flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5" aria-hidden="true">
                <path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/>
            </svg>
        </span>
    @endif

    <div class="{{ $isMe ? 'flex flex-col items-end' : 'flex-1 min-w-0' }}">
        @unless ($isMe)
            <div class="text-[11px] font-bold text-ink-700 mb-1 px-0.5">
                AI
                @if ($isPending)
                    <span class="font-medium text-ink-500">応答中...</span>
                @endif
            </div>
        @endunless

        <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed
            @if ($isError) bg-danger-50 border border-danger-200 text-danger-900
            @elseif ($isMe) bg-secondary-600 text-white rounded-br-md shadow
            @else bg-white text-ink-900 rounded-tl-md shadow-sm border border-subtle
            @endif">
            @if ($isPending && $message->content === '')
                <span class="inline-flex gap-1 py-1.5" aria-label="応答を生成中">
                    <span class="w-1.5 h-1.5 rounded-full bg-secondary-400 animate-bounce" style="animation-delay: 0ms;"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-secondary-400 animate-bounce" style="animation-delay: 160ms;"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-secondary-400 animate-bounce" style="animation-delay: 320ms;"></span>
                </span>
            @elseif ($isError && $message->content === '')
                @php
                    $errorDetail = (string) ($message->error_detail ?? '');
                    $errorText = match (true) {
                        str_contains($errorDetail, '429') => 'Gemini API のリクエスト制限に達しました。1 分ほど待って再試行してください。',
                        str_contains($errorDetail, '503') || str_contains($errorDetail, '502') || str_contains($errorDetail, '500') => 'Gemini API が一時的に応答していません。少し待って再試行してください。',
                        default => 'AI が応答できませんでした。しばらく時間をおいて再試行してください。',
                    };
                @endphp
                <div class="whitespace-pre-wrap break-words" data-message-content>{{ $errorText }}</div>
            @elseif ($isMe)
                <div class="whitespace-pre-wrap break-words" data-message-content>{{ $message->content }}</div>
            @else
                {{-- assistant メッセージは DOMContentLoaded で Markdown → サニタイズ済 HTML に変換 (resources/js/ai-chat/index.js) --}}
                <div class="break-words" data-message-content data-markdown>{{ $message->content }}</div>
            @endif

            @if ($isError)
                <button type="button"
                    class="mt-2 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-white border border-danger-200 text-danger-700 text-xs font-semibold hover:bg-danger-50"
                    data-action="retry"
                    data-message-id="{{ $message->id }}"
                    data-retry-url="{{ route('ai-chat.messages.retry', $message) }}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.36m0 0L17.59 7.69A8.25 8.25 0 1 0 6.343 17.657"/>
                    </svg>
                    再送信
                </button>
            @endif
        </div>

        <div class="text-[11px] text-ink-400 mt-1 px-0.5 tabular-nums">
            @if ($isError)
                {{ $message->updated_at?->format('H:i') }} · エラー
            @elseif (! $isMe && $message->status === \App\Enums\AiChatMessageStatus::Completed)
                {{ $message->created_at?->format('H:i') }}
                @if ($message->response_time_ms)
                    · {{ number_format($message->response_time_ms / 1000, 1) }} s
                @endif
                @if ($message->output_tokens)
                    · {{ number_format($message->output_tokens) }} tokens
                @endif
            @else
                {{ $message->created_at?->format('H:i') }}
            @endif
        </div>
    </div>
</li>
